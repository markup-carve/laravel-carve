<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use Composer\InstalledVersions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use MarkupCarve\Carve2\Carve2;
use MarkupCarve\Carve2\Include\FileResolver;
use MarkupCarve\Carve2\Node;
use MarkupCarve\Carve2\Output\OutputWriter;
use MarkupCarve\Carve2\Output\WriterRegistry;
use MarkupCarve\LaravelCarve\RenderedCarve;
use Psr\Log\LoggerInterface;
use Throwable;

class CarveConverter implements CarveConverterInterface
{
    /**
     * @var string
     */
    public const OUTSIDE_ROOT = '[outside-root]';

    /**
     * @var array<string, mixed>
     */
    private array $options;

    private ?string $includeRoot = null;

    private WriterRegistry $writers;

    private string $cacheSignature;

    /**
     * @param array<string, mixed> $options
     * @param string|null $cacheVersion
     * @param string $cachePrefix
     * @param int|null $cacheTtl
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param string|null $includeRoot
     * @param \Illuminate\Contracts\Cache\Repository|null $cache
     * @param list<\MarkupCarve\Carve2\Output\OutputWriter> $writers
     */
    public function __construct(
        array $options = [],
        private ?CacheRepository $cache = null,
        ?string $includeRoot = null,
        private ?LoggerInterface $logger = null,
        private ?int $cacheTtl = null,
        private string $cachePrefix = 'laravel_carve',
        ?string $cacheVersion = null,
        array $writers = [],
    ) {
        if ($includeRoot !== null) {
            $root = realpath($includeRoot);
            if ($root === false || !is_dir($root) || !str_starts_with($root, '/')) {
                throw new InvalidArgumentException('carve.include_root must be an existing absolute directory.');
            }
            $this->includeRoot = rtrim($root, DIRECTORY_SEPARATOR) ?: '/';
        }
        $this->options = $options;
        $this->writers = Carve2::writers();
        foreach ($writers as $writer) {
            $this->registerWriter($writer);
        }
        try {
            $signatureOptions = $options;
            unset($signatureOptions['includes']);
            $state = serialize([$signatureOptions, $this->includeRoot]);
        } catch (Throwable) {
            $state = '';
            $this->cache = null;
        }
        $engineRef = InstalledVersions::getReference('markup-carve/carve-php') ?? 'unknown';
        $this->cacheSignature = hash('xxh3', Carve2::class . $engineRef . $state . $cacheVersion);
    }

    public function registerWriter(OutputWriter $writer): void
    {
        $this->writers->register($writer);
        $this->cache = null;
    }

    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
        $this->cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function toHtml(string $carve): string
    {
        return $this->render($carve)->html;
    }

    public function render(string $carve): RenderedCarve
    {
        $key = $this->cachePrefix . '_render_' . $this->cacheSignature . '_' . hash('xxh3', $carve);
        $cached = $this->cache?->get($key);
        if ($cached instanceof RenderedCarve) {
            return $cached;
        }
        $result = Carve2::renderWithDiagnostics($carve, $this->options);
        [$toc, $frontmatter] = $this->metadata(Carve2::parse($carve, $this->parseOptions()));
        $rendered = new RenderedCarve(
            $carve,
            $result->html,
            $toc,
            $frontmatter,
            warnings: $this->warnings($result->diagnostics, $carve),
        );
        $this->cache?->put($key, $rendered, $this->cacheTtl);

        return $rendered;
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
        [$realPath, $source] = $this->readFile($path);
        $options = $this->fileOptions($realPath);
        $key = $this->cachePrefix . '_file_' . $this->cacheSignature . '_' . hash('xxh3', $realPath . $source);
        $cached = $this->cache?->get($key);
        if (
            is_array($cached) && isset($cached['states'], $cached['rendered'])
            && $cached['states'] === $this->dependencyStates(array_map(strval(...), array_keys($cached['states'])))
        ) {
            return $cached['rendered'];
        }

        $result = Carve2::renderWithDiagnostics($source, $options);
        $parseOptions = $this->parseOptions($options);
        $tree = Carve2::parse($source, $parseOptions);
        $entries = $this->includeRoot === null ? [] : Carve2::includeDependencies($tree, $parseOptions);
        $dependencies = [];
        $targets = [];
        foreach ($entries as $entry) {
            $identity = $entry['file'] ?? $entry['path'];
            $dependencies[] = ['path' => $this->containedIdentity($identity), 'resolved' => $entry['read']];
            $targets[] = $this->dependencyPath($entry, $realPath);
        }
        $warnings = $this->warnings($result->diagnostics, $source);
        foreach ($warnings as $warning) {
            $this->logger?->warning('Carve diagnostic: {message}', $warning);
        }
        [$toc, $frontmatter] = $this->metadata($tree);
        $rendered = new RenderedCarve(
            $source,
            $result->html,
            $toc,
            $frontmatter,
            warnings: $warnings,
            dependencies: $dependencies,
        );
        $this->cache?->put($key, ['rendered' => $rendered, 'states' => $this->dependencyStates($targets)], $this->cacheTtl);

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
        if (
            $this->includeRoot !== null && $realPath !== $this->includeRoot
            && !str_starts_with($realPath, $this->includeRoot . DIRECTORY_SEPARATOR)
        ) {
            throw new InvalidArgumentException('Carve source must be inside carve.include_root.');
        }

        return [$realPath, $source];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileOptions(string $path): array
    {
        $options = $this->options;
        if ($this->includeRoot !== null) {
            $options['includes'] = ['resolver' => FileResolver::forRoot($this->includeRoot), 'file' => $path];
        }

        return $options;
    }

    /**
     * Only options consumed by parse and resolve may enter parse calls.
     *
     * @param array<string, mixed>|null $options
     *
     * @return array<string, mixed>
     */
    private function parseOptions(?array $options = null): array
    {
        $options ??= $this->options;

        return array_intersect_key($options, array_flip([
            'includes', 'extensions', 'headingIds',
        ]));
    }

    /**
     * @return array{list<array{level: int, text: string, html: string, id: string}>, array{format: string, content: string}|null}
     */
    private function metadata(Node $tree): array
    {
        $toc = [];
        $frontmatter = null;
        $stack = [$tree];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node->type === 'heading') {
                $text = $this->nodeText($node);
                $level = $node->f['level'] ?? 1;
                $id = $node->f['generatedId'] ?? '';
                $toc[] = [

                    'level' => is_int($level) ? $level : 1,
                    'text' => $text,
                    'html' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'id' => is_string($id) ? $id : '',
                ];
            } elseif ($node->type === 'frontmatter') {
                $format = $node->f['format'] ?? '';
                $content = $node->f['value'] ?? '';
                $frontmatter = [

                    'format' => is_string($format) ? $format : '',
                    'content' => is_string($content) ? $content : '',
                ];
            }
            foreach (array_reverse($node->children ?? []) as $child) {
                $stack[] = $child;
            }
        }

        return [$toc, $frontmatter];
    }

    private function nodeText(Node $node): string
    {
        if ($node->type === 'text') {
            $value = $node->f['value'] ?? '';

            return is_string($value) ? $value : '';
        }
        $text = '';
        foreach ($node->children ?? [] as $child) {
            $text .= $this->nodeText($child);
        }

        return $text;
    }

    /**
     * @param array{path: string, from: string|null, file: string|null, read: bool} $entry
     * @param string $file
     */
    private function dependencyPath(array $entry, string $file): string
    {
        if ($this->includeRoot === null) {
            return '';
        }
        if (is_string($entry['file'] ?? null)) {
            return $this->includeRoot . '/' . $entry['file'];
        }
        $from = is_string($entry['from'] ?? null) ? $entry['from'] : $file;
        $base = str_starts_with($from, '/') ? dirname($from) : $this->includeRoot . '/' . dirname($from);

        return $base . '/' . $entry['path'];
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, string|null>
     */
    private function dependencyStates(array $paths): array
    {
        $states = [];
        foreach ($paths as $path) {
            $real = realpath($path);
            $inside = $this->includeRoot !== null && $real !== false
                && str_starts_with($real, $this->includeRoot . DIRECTORY_SEPARATOR);
            $states[$path] = $inside && is_file($real) ? (hash_file('xxh3', $real) ?: null) : null;
        }

        return $states;
    }

    private function containedIdentity(string $identity): string
    {
        if ($this->includeRoot === null) {
            return $identity;
        }
        if (str_starts_with($identity, $this->includeRoot . '/')) {
            return substr($identity, strlen($this->includeRoot) + 1);
        }
        if (str_starts_with($identity, '/') || in_array('..', explode('/', $identity), true)) {
            return self::OUTSIDE_ROOT;
        }

        return $identity;
    }

    /**
     * @param array<\MarkupCarve\Carve2\Diagnostics\Diagnostic> $diagnostics
     * @param string $source
     *
     * @return list<array<string, mixed>>
     */
    private function warnings(array $diagnostics, string $source): array
    {
        $warnings = [];
        foreach ($diagnostics as $diagnostic) {
            $warningSource = $source;
            $identity = $diagnostic->file === null ? null : $this->containedIdentity($diagnostic->file);
            if ($this->includeRoot !== null && $identity !== null && $identity !== self::OUTSIDE_ROOT) {
                $candidate = realpath($this->includeRoot . '/' . $identity);
                if (
                    $candidate !== false && is_file($candidate)
                    && str_starts_with($candidate, $this->includeRoot . '/')
                ) {
                    $included = file_get_contents($candidate);
                    if ($included !== false) {
                        $warningSource = $included;
                    }
                }
            }
            $offset = max(0, $diagnostic->startOffset);
            $prefix = substr($warningSource, 0, $offset);
            $line = substr_count($prefix, "\n") + 1;
            $previous = strrpos($prefix, "\n");
            $column = $offset - ($previous === false ? -1 : $previous);
            $warnings[] = [

                'rule' => $diagnostic->code,
                'message' => $diagnostic->message,
                'file' => $identity,
                'line' => $line,
                'column' => $column,
            ];
        }

        return $warnings;
    }

    public function renderFileAs(string $path, string $format): string
    {
        if ($format === 'html') {
            return $this->renderFile($path)->html;
        }
        [$realPath, $source] = $this->readFile($path);

        return $this->writeTarget($format, $source, $this->fileOptions($realPath));
    }

    public function toText(string $carve): string
    {
        return $this->writeTarget('text', $carve);
    }

    public function toMarkdown(string $carve): string
    {
        return $this->writeTarget('markdown', $carve);
    }

    public function toAnsi(string $carve): string
    {
        return $this->writeTarget('ansi', $carve);
    }

    /**
     * @param string $format
     * @param string $source
     * @param array<string, mixed>|null $options
     *
     * @throws \InvalidArgumentException
     */
    private function writeTarget(string $format, string $source, ?array $options = null): string
    {
        if (!$this->writers->has($format)) {
            throw new InvalidArgumentException(sprintf('Carve %s writer not available yet.', $format));
        }
        $options ??= $this->options;
        $tree = Carve2::parse($source, $this->parseOptions($options));

        return Carve2::writeTo($format, $tree, $options, $this->writers);
    }

    public function parse(string $carve): Node
    {
        return Carve2::parse($carve, $this->parseOptions());
    }
}
