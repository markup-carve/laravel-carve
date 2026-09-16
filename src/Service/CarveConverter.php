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

class CarveConverter implements CarveConverterInterface
{
    private BaseCarveConverter $converter;

    private PlainTextRenderer $textRenderer;

    private string $cacheSignature;

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
        private ?string $includeRoot = null,
        private ?LoggerInterface $logger = null,
    ) {
        if (
            $this->includeRoot !== null && !str_starts_with($this->includeRoot, '/')
            && preg_match('/^[A-Za-z]:[\\\\\/]/', $this->includeRoot) !== 1
        ) {
            throw new InvalidArgumentException('carve.include_root must be an absolute path.');
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
        if ($this->includeRoot === null) {
            return ['value' => $this->toHtml($source), 'warnings' => [], 'dependencies' => [], 'suppressedWarnings' => 0];
        }

        $root = realpath($this->includeRoot);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException('carve.include_root is not a readable directory.');
        }
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if ($sourcePath !== $root && !str_starts_with($sourcePath, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Carve source must be inside carve.include_root.');
        }

        $expander = new IncludeExpander(
            resolver: new FilesystemIncludeResolver($root),
            currentPath: $sourcePath,
            source: $source,
        );
        $document = $this->converter->transform($this->converter->parse($source), $expander);
        $warnings = array_map(fn ($warning): array => [
            'rule' => $warning->getRule(),
            'message' => $warning->getMessage(),
            'file' => $this->relativeIdentity($warning->getFile(), $root),
            'line' => $warning->getLine(),
            'column' => $warning->getColumn(),
        ], $expander->getWarnings());
        $dependencies = array_map(fn ($dependency): array => [
            'path' => $this->relativeIdentity($dependency->getTarget(), $root) ?? '[unknown]',
            'resolved' => $dependency->isResolved(),
        ], $expander->getDependencies());
        foreach ($warnings as $warning) {
            $this->logger?->warning('Carve include warning: {message}', $warning);
        }

        $html = $this->converter->render($document);
        if ($this->cache !== null) {
            $states = array_map(function (array $dependency) use ($root): array {
                $candidate = $root . DIRECTORY_SEPARATOR . $dependency['path'];

                return [$dependency['path'], is_file($candidate) ? hash_file('xxh3', $candidate) : null];
            }, $dependencies);
            $key = 'laravel_carve_file_' . $this->cacheSignature . '_' . hash('xxh3', serialize([$sourcePath, hash('xxh3', $source), $states]));
            /** @var string|null $cached */
            $cached = $this->cache->get($key);
            if ($cached !== null) {
                $html = $cached;
            } else {
                $this->cache->forever($key, $html);
            }
        }

        return ['value' => $html, 'warnings' => $warnings, 'dependencies' => $dependencies, 'suppressedWarnings' => $expander->getSuppressedWarnings()];
    }

    private function relativeIdentity(?string $identity, string $root): ?string
    {
        if ($identity === null) {
            return null;
        }
        if (!str_starts_with($identity, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $identity) !== 1) {
            $identity = str_replace('\\', '/', $identity);

            return $identity === '..' || str_starts_with($identity, '../') ? '[outside-root]' : $identity;
        }
        if (str_starts_with($identity, $root . DIRECTORY_SEPARATOR)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($identity, strlen($root) + 1));
        }

        return '[outside-root]';
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
