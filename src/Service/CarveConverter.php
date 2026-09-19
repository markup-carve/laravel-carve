<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter as BaseCarveConverter;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use MarkupCarve\Carve\Renderer\RenderMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Carve\Transform\IncludeExpander;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
     * @param bool $safeMode
     * @param string $mode Render mode: 'interactive' (default) or 'static'
     *   (graceful degradation for print/email/PDF targets)
     * @param string|null $softBreakMode
     * @param bool $xhtml
     * @param \Illuminate\Contracts\Cache\Repository|null $cache
     * @param array<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     * @param array<string, string> $symbols Trusted, unescaped HTML replacements for `:name:` symbols
     * @param bool $sourceLines
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string|null $includeRoot
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        bool $safeMode = true,
        string $mode = RenderMode::INTERACTIVE,
        ?string $softBreakMode = null,
        bool $xhtml = false,
        private ?CacheRepository $cache = null,
        array $extensions = [],
        array $symbols = [],
        bool $sourceLines = false,
        ?string $includeRoot = null,
        private ?LoggerInterface $logger = null,
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
        );
        $this->textRenderer = new PlainTextRenderer();

        foreach ($extensions as $extension) {
            $this->converter->addExtension($extension);
        }

        // The cache is a shared store, so the key has to identify the converter
        // as well as the source. Two named profiles rendering the same string -
        // say a safe one for comments and an unsafe one for admin content -
        // would otherwise collide, and whichever rendered first would serve the
        // other its HTML.
        $this->cacheSignature = hash('xxh3', serialize([
            $safeMode,
            $mode,
            $softBreakMode,
            $xhtml,
            $symbols,
            $sourceLines,
            array_map(static fn (object $extension): string => $extension::class, $extensions),
        ]));
    }

    public function toHtml(string $carve): string
    {
        if ($this->cache !== null) {
            $cacheKey = 'laravel_carve_html_' . $this->cacheSignature . '_' . hash('xxh3', $carve);

            /** @var string|null $cached */
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $html = $this->converter->convert($carve);
            $this->cache->forever($cacheKey, $html);

            return $html;
        }

        return $this->converter->convert($carve);
    }

    public function toText(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return $this->textRenderer->render($document);
    }

    public function toHtmlFile(string $path): string
    {
        return $this->toHtmlFileWithReport($path)['value'];
    }

    public function toHtmlFileWithReport(string $path): array
    {
        $sourcePath = realpath($path);
        if ($sourcePath === false || !is_file($sourcePath)) {
            throw new InvalidArgumentException(sprintf('Carve source is not a readable file: %s', $path));
        }
        $source = file_get_contents($sourcePath);
        if ($source === false) {
            throw new InvalidArgumentException(sprintf('Carve source is not readable: %s', $path));
        }
        $root = $this->includeRoot;
        if ($root === null || $this->includeResolver === null) {
            return ['value' => $this->toHtml($source), 'warnings' => [], 'dependencies' => [], 'suppressedWarnings' => 0];
        }

        if ($sourcePath !== $root && !str_starts_with($sourcePath, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Carve source must be inside carve.include_root.');
        }

        $cache = $this->cache;
        $cacheKey = null;
        if ($cache !== null) {
            $cacheKey = 'laravel_carve_file_' . $this->cacheSignature . '_' . hash('xxh3', serialize([$sourcePath, hash('xxh3', $source)]));
            /** @var array{value: string, warnings: list<array<string, mixed>>, dependencies: list<array{path: string, resolved: bool}>, suppressedWarnings: int, states: array<string, string|null>}|null $cached */
            $cached = $cache->get($cacheKey);
            if ($cached !== null && $cached['states'] === $this->dependencyStates(array_keys($cached['states']), $root)) {
                unset($cached['states']);

                return $cached;
            }
        }

        $expander = new IncludeExpander(
            resolver: $this->includeResolver,
            currentPath: $sourcePath,
            source: $source,
        );
        $document = $this->converter->transform($this->converter->parse($source), $expander);
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

        $result = [
            'value' => $this->converter->render($document),
            'warnings' => $warnings,
            'dependencies' => $dependencies,
            'suppressedWarnings' => $expander->getSuppressedWarnings(),
        ];
        if ($cache !== null) {
            $cache->forever($cacheKey, $result + ['states' => $this->dependencyStates($targets, $root)]);
        }

        return $result;
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
        return $this->converter;
    }
}
