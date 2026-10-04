<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter as BaseCarveConverter;
use MarkupCarve\Carve\Extension\FrontmatterExtension;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use MarkupCarve\Carve\Renderer\RenderMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use MarkupCarve\Carve\SafeMode;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Carve\Transform\IncludeExpander;
use MarkupCarve\LaravelCarve\RenderedCarve;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class CarveConverter implements CarveConverterInterface
{
    /**
     * Stand-in for an identity that names anything but a path inside the root.
     *
     * @var string
     */
    public const OUTSIDE_ROOT = '[outside-root]';

    private BaseCarveConverter $converter;

    private PlainTextRenderer $textRenderer;

    private string $cacheSignature;

    /**
     * Canonical containment root, or null while inclusion is disabled.
     */
    private ?string $includeRoot = null;

    private ?FilesystemIncludeResolver $includeResolver = null;

    /**
     * @param \MarkupCarve\Carve\SafeMode|bool $safeMode
     * @param string $mode Render mode: 'interactive' (default) or 'static'
     *   (graceful degradation for print/email/PDF targets)
     * @param string|null $softBreakMode
     * @param bool $xhtml
     * @param \Illuminate\Contracts\Cache\Repository|null $cache
     * @param array<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     * @param array<string, string> $symbols Trusted, unescaped HTML replacements for `:name:` symbols
     * @param bool $sourceLines
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string|null $cacheVersion
     * @param string $cachePrefix
     * @param int|null $cacheTtl
     * @param array<string, string> $labels
     * @param bool|null $smartTypography
     * @param string|null $onDisallowed
     * @param string|null $preset
     * @param string|null $includeRoot
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        SafeMode|bool $safeMode = true,
        string $mode = RenderMode::INTERACTIVE,
        ?string $softBreakMode = null,
        bool $xhtml = false,
        private ?CacheRepository $cache = null,
        array $extensions = [],
        array $symbols = [],
        bool $sourceLines = false,
        ?string $includeRoot = null,
        private ?LoggerInterface $logger = null,
        ?string $preset = null,
        ?string $onDisallowed = null,
        ?bool $smartTypography = null,
        array $labels = [],
        private ?int $cacheTtl = null,
        private string $cachePrefix = 'laravel_carve',
        ?string $cacheVersion = null,
    ) {
        if ($includeRoot !== null) {
            // The resolver owns the rule that a configured root must be
            // absolute, so a value it refuses never reaches a render. Naming
            // the config key here is the only thing added.
            try {
                $this->includeResolver = new FilesystemIncludeResolver($includeRoot);
            } catch (RuntimeException $exception) {
                throw new InvalidArgumentException('carve.include_root: ' . $exception->getMessage(), 0, $exception);
            }
            $this->includeRoot = rtrim((string)realpath($includeRoot), DIRECTORY_SEPARATOR);
        }
        $this->converter = new BaseCarveConverter(
            xhtml: $xhtml,
            safeMode: $safeMode,
            mode: $mode,
            softBreakMode: $softBreakMode !== null ? SoftBreakMode::from($softBreakMode) : null,
            symbols: $symbols,
            sourceLines: $sourceLines,
            profile: RenderPolicy::preset($preset, $onDisallowed),
            smartTypography: $smartTypography,
            labels: $labels,
        );
        $this->textRenderer = new PlainTextRenderer();

        foreach ($extensions as $extension) {
            $this->converter->addExtension($extension);
        }

        if (!array_filter($extensions, static fn ($extension): bool => $extension instanceof TableOfContentsExtension)) {
            $this->converter->addExtension(new TableOfContentsExtension());
        }

        // A signature must include extension options and engine changes. Extensions
        // containing closures need an explicit application version to enable caching.
        try {
            $extensionState = serialize($extensions);
        } catch (Throwable) {
            $extensionState = array_map(static fn ($extension): string => $extension::class, $extensions);
            if ($cacheVersion === null) {
                $this->cache = null;
            }
        }
        $this->cacheSignature = hash('xxh3', serialize([
            BaseCarveConverter::LIB_VERSION, $safeMode, $mode, $softBreakMode, $xhtml,
            $symbols, $sourceLines, $preset, $onDisallowed, $smartTypography, $labels,
            $includeRoot, $extensionState, $cacheVersion,
        ]));
    }

    public function toHtml(string $carve): string
    {
        return $this->render($carve)->html;
    }

    public function render(string $carve): RenderedCarve
    {
        $cacheKey = $this->cachePrefix . '_render_' . $this->cacheSignature . '_' . hash('xxh3', $carve);
        $cached = $this->cache?->get($cacheKey);
        if ($cached instanceof RenderedCarve) {
            return $cached;
        }
        $rendered = $this->renderDocument($carve, $this->converter->parse($carve));
        $this->cache?->put($cacheKey, $rendered, $this->cacheTtl);

        return $rendered;
    }

    /**
     * @param string $source
     * @param \MarkupCarve\Carve\Node\Document $document
     * @param list<array<string, mixed>> $warnings
     * @param list<array{path: string, resolved: bool}> $dependencies
     * @param int $suppressedWarnings
     */
    private function renderDocument(
        string $source,
        Document $document,
        array $warnings = [],
        array $dependencies = [],
        int $suppressedWarnings = 0,
    ): RenderedCarve {
        $html = $this->converter->render($document);
        $toc = [];
        $frontmatter = null;
        foreach ($this->converter->getExtensions() as $extension) {
            if ($extension instanceof TableOfContentsExtension) {
                $toc = $extension->getToc();
            }
            if ($extension instanceof FrontmatterExtension && $extension->hasFrontmatter()) {
                $frontmatter = ['format' => (string)$extension->getFormat(), 'content' => (string)$extension->getContent()];
            }
        }

        return new RenderedCarve($source, $html, $toc, $frontmatter, $warnings, $dependencies, $suppressedWarnings);
    }

    public function toText(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return $this->textRenderer->render($document);
    }

    public function toHtmlFile(string $path): string
    {
        return $this->renderFile($path)->html;
    }

    public function toHtmlFileWithReport(string $path): array
    {
        $rendered = $this->renderFile($path);

        return [
            'value' => $rendered->html,
            'warnings' => $rendered->warnings,
            'dependencies' => $rendered->dependencies,
            'suppressedWarnings' => $rendered->suppressedWarnings,
        ];
    }

    public function renderFile(string $path): RenderedCarve
    {
        [$sourcePath, $source] = $this->readFile($path);
        $root = $this->includeRoot;
        if ($root === null || $this->includeResolver === null) {
            return $this->render($source);
        }

        $cache = $this->cache;
        $cacheKey = null;
        if ($cache !== null) {
            $cacheKey = $this->cachePrefix . '_file_' . $this->cacheSignature . '_' . hash('xxh3', serialize([$sourcePath, hash('xxh3', $source)]));
            /** @var array{rendered: \MarkupCarve\LaravelCarve\RenderedCarve, states: array<string, string|null>}|null $cached */
            $cached = $cache->get($cacheKey);
            if ($cached !== null && $cached['states'] === $this->dependencyStates(array_keys($cached['states']), $root)) {
                return $cached['rendered'];
            }
        }

        [$document, $expander] = $this->expandFile($sourcePath, $source);
        $warnings = array_map(fn ($warning): array => [
            'rule' => $warning->getRule(),
            'message' => $warning->getMessage(),
            'file' => $this->containedIdentity($warning->getFile(), $root),
            'line' => $warning->getLine(),
            'column' => $warning->getColumn(),
        ], $expander->getWarnings());
        $targets = array_map(static fn ($dependency): string => $dependency->getTarget(), $expander->getDependencies());
        $dependencies = array_map(fn ($dependency): array => [
            'path' => $this->containedIdentity($dependency->getTarget(), $root) ?? self::OUTSIDE_ROOT,
            'resolved' => $dependency->isResolved(),
        ], $expander->getDependencies());
        foreach ($warnings as $warning) {
            $this->logger?->warning('Carve include warning: {message}', $warning);
        }

        $rendered = $this->renderDocument($source, $document, $warnings, $dependencies, $expander->getSuppressedWarnings());
        if ($cache !== null) {
            $cache->put($cacheKey, ['rendered' => $rendered, 'states' => $this->dependencyStates($targets, $root)], $this->cacheTtl);
        }

        return $rendered;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array{string, string}
     */
    private function readFile(string $path): array
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            throw new InvalidArgumentException(sprintf('Carve source is not a readable file: %s', $path));
        }
        $source = file_get_contents($realPath);
        if ($source === false) {
            throw new InvalidArgumentException(sprintf('Carve source is not a readable file: %s', $path));
        }
        $root = $this->includeRoot;
        if ($root !== null && !str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Carve source must be inside carve.include_root.');
        }

        return [$realPath, $source];
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array{\MarkupCarve\Carve\Node\Document, \MarkupCarve\Carve\Transform\IncludeExpander}
     */
    private function expandFile(string $path, string $source): array
    {
        $resolver = $this->includeResolver;
        if ($resolver === null) {
            throw new InvalidArgumentException('Carve include resolution is disabled.');
        }
        $document = $this->converter->parse($source);
        $expander = new IncludeExpander(
            resolver: $resolver,
            currentPath: $path,
            source: $source,
            extensions: $this->converter->getExtensions(),
        );

        return [$this->converter->transform($document, $expander), $expander];
    }

    public function renderFileAs(string $path, string $format): string
    {
        if ($format === 'html') {
            return $this->renderFile($path)->html;
        }
        [$realPath, $source] = $this->readFile($path);
        $document = $this->includeResolver === null
            ? $this->converter->parse($source)
            : $this->expandFile($realPath, $source)[0];

        return match ($format) {
            'text' => $this->textRenderer->render($document),
            'markdown' => (new MarkdownRenderer())->render($document),
            'ansi' => (new AnsiRenderer())->render($document),
            default => throw new InvalidArgumentException('Unknown Carve output format.'),
        };
    }

    /**
     * Content hash per include target, or null where the target is absent or
     * outside the root. A missing target keeps an entry on purpose: creating
     * the file is what makes the directive start working, so it has to
     * invalidate the entry.
     *
     * @param array<string> $targets
     * @param string $root
     *
     * @return array<string, string|null>
     */
    private function dependencyStates(array $targets, string $root): array
    {
        $states = [];
        foreach ($targets as $target) {
            $contained = $this->containedIdentity($target, $root);
            $candidate = $contained === null || $contained === self::OUTSIDE_ROOT
                ? null
                : $root . DIRECTORY_SEPARATOR . $contained;
            $states[$target] = $candidate !== null && is_file($candidate)
                ? (hash_file('xxh3', $candidate) ?: null)
                : null;
        }

        return $states;
    }

    /**
     * The identity as a path relative to the root, or a fixed marker when it
     * names anything else. A resolver message may embed an absolute path, and
     * a denial keeps the directive's own spelling, so neither is reported.
     */
    private function containedIdentity(?string $identity, string $root): ?string
    {
        if ($identity === null) {
            return null;
        }
        if (str_starts_with($identity, $root . DIRECTORY_SEPARATOR)) {
            $identity = substr($identity, strlen($root) + 1);
        } elseif (str_starts_with($identity, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $identity) === 1) {
            return self::OUTSIDE_ROOT;
        }
        $identity = str_replace('\\', '/', $identity);

        return in_array('..', explode('/', $identity), true) ? self::OUTSIDE_ROOT : $identity;
    }

    public function toMarkdown(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return (new MarkdownRenderer())->render($document);
    }

    public function toAnsi(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return (new AnsiRenderer())->render($document);
    }

    public function parse(string $carve): Document
    {
        return $this->converter->parse($carve);
    }

    public function getConverter(): BaseCarveConverter
    {
        $this->cache = null;

        return $this->converter;
    }
}
