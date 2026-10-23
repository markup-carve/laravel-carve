<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use Closure;
use InvalidArgumentException;
use MarkupCarve\Carve\CarveConverter as EngineConverter;
use MarkupCarve\Carve\Converter\HtmlToCarve;
use MarkupCarve\Carve\Converter\MarkdownToCarve;
use MarkupCarve\Carve\Lint\DocumentLinter;
use MarkupCarve\Carve\Lint\IncludeLinter;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\LaravelCarve\RenderedCarve;

class CarveManager
{
    private ?CarveConverterInterface $rawConverter = null;

    /**
     * @var array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface>
     */
    private array $resolved = [];

    /**
     * @var array<string, list<\Closure(\MarkupCarve\Carve\CarveConverter): void>>
     */
    private array $customizers = [];

    /**
     * @var array<string, int>
     */
    private array $appliedCustomizers = [];

    /**
     * @param array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface|\Closure(): \MarkupCarve\LaravelCarve\Service\CarveConverterInterface> $converters
     * @param string $defaultConverter
     */
    public function __construct(private array $converters, private string $defaultConverter = 'default')
    {
    }

    /**
     * Convert Carve markup to HTML using the named converter (or default).
     */
    public function toHtml(string $carve, ?string $converter = null): string
    {
        return $this->getConverter($converter)->toHtml($carve);
    }

    public function toHtmlFile(string $path, ?string $converter = null): string
    {
        return $this->getConverter($converter)->toHtmlFile($path);
    }

    /**
     * @return array{value: string, warnings: list<array<string, mixed>>, dependencies: list<array{path: string, resolved: bool}>, suppressedWarnings: int}
     */
    public function toHtmlFileWithReport(string $path, ?string $converter = null): array
    {
        return $this->getConverter($converter)->toHtmlFileWithReport($path);
    }

    /**
     * Convert Carve markup to HTML without safe mode (trusted content only).
     */
    public function toHtmlRaw(string $carve): string
    {
        return $this->getRawConverter()->toHtml($carve);
    }

    /**
     * Convert Carve markup to plain text using the named converter (or default).
     */
    public function toText(string $carve, ?string $converter = null): string
    {
        return $this->getConverter($converter)->toText($carve);
    }

    /**
     * Render Carve markup as Markdown using the named converter (or default).
     */
    public function toMarkdown(string $carve, ?string $converter = null): string
    {
        return $this->getConverter($converter)->toMarkdown($carve);
    }

    /**
     * Render Carve markup as ANSI terminal output using the named converter
     * (or default).
     */
    public function toAnsi(string $carve, ?string $converter = null): string
    {
        return $this->getConverter($converter)->toAnsi($carve);
    }

    /**
     * Get a named converter instance.
     */
    public function converter(?string $name = null): CarveConverterInterface
    {
        return $this->getConverter($name);
    }

    /**
     * @return array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface>
     */
    public function getConverters(): array
    {
        $result = [];
        foreach (array_keys($this->converters) as $name) {
            $result[$name] = $this->getConverter($name);
        }

        return $result;
    }

    public function profile(?string $name = null): CarveConverterInterface
    {
        return $this->getConverter($name);
    }

    public function getDefaultProfile(): string
    {
        return $this->defaultConverter;
    }

    /**
     * @return list<string>
     */
    public function profiles(): array
    {
        return array_keys($this->converters);
    }

    /**
     * @param string $name
     * @param \Closure(\MarkupCarve\Carve\CarveConverter): void $callback
     *
     * @throws \InvalidArgumentException
     */
    public function extend(string $name, Closure $callback): static
    {
        if (!isset($this->converters[$name])) {
            throw new InvalidArgumentException(sprintf('Carve converter "%s" not found.', $name));
        }
        $this->customizers[$name][] = $callback;
        unset($this->resolved[$name]);

        return $this;
    }

    public function flush(): void
    {
        $this->resolved = [];
    }

    public function render(string $source, ?string $converter = null): RenderedCarve
    {
        $instance = $this->getConverter($converter);

        return $instance instanceof CarveConverter
            ? $instance->render($source)
            : new RenderedCarve($source, $instance->toHtml($source));
    }

    public function renderFile(string $path, ?string $converter = null): RenderedCarve
    {
        $instance = $this->getConverter($converter);
        if ($instance instanceof CarveConverter) {
            return $instance->renderFile($path);
        }
        $source = file_get_contents($path);
        if ($source === false) {
            throw new InvalidArgumentException('Carve source is not readable.');
        }
        $report = $instance->toHtmlFileWithReport($path);

        return new RenderedCarve($source, $report['value'], warnings: $report['warnings'], dependencies: $report['dependencies'], suppressedWarnings: $report['suppressedWarnings']);
    }

    public function parse(string $source, ?string $converter = null): Document
    {
        return $this->getConverter($converter)->parse($source);
    }

    public function fromMarkdown(string $source): string
    {
        return (new MarkdownToCarve())->convert($source);
    }

    public function fromHtml(string $source): string
    {
        return (new HtmlToCarve())->convert($source);
    }

    /**
     * Canonical Carve source, as `carve fmt` prints it.
     */
    public function format(string $source, bool $alignTables = false): string
    {
        return EngineConverter::toCarve($source, $alignTables);
    }

    /**
     * With an include root, included files are linted too and their findings
     * carry `file` and `includedBy`.
     *
     * @return list<\MarkupCarve\Carve\Lint\LintWarning>
     */
    public function lint(string $source, ?string $includeRoot = null, ?string $sourcePath = null): array
    {
        if ($includeRoot === null) {
            return (new DocumentLinter())->lint($source);
        }

        return (new IncludeLinter())->lint($source, [], new FilesystemIncludeResolver($includeRoot), $sourcePath);
    }

    private function getConverter(?string $name): CarveConverterInterface
    {
        $name ??= $this->defaultConverter;
        if (!isset($this->converters[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Carve converter "%s" not found. Available converters: %s',
                $name,
                implode(', ', array_keys($this->converters)),
            ));
        }

        if (!isset($this->resolved[$name])) {
            $definition = $this->converters[$name];
            $converter = $definition instanceof Closure ? $definition() : $definition;
            $callbacks = $this->customizers[$name] ?? [];
            $applied = $definition instanceof Closure ? 0 : ($this->appliedCustomizers[$name] ?? 0);
            foreach (array_slice($callbacks, $applied) as $customizer) {
                $customizer($converter->getConverter());
            }
            if (!$definition instanceof Closure) {
                $this->appliedCustomizers[$name] = count($callbacks);
            }
            $this->resolved[$name] = $converter;
        }

        return $this->resolved[$name];
    }

    private function getRawConverter(): CarveConverterInterface
    {
        if ($this->rawConverter === null) {
            $this->rawConverter = new CarveConverter(safeMode: false);
        }

        return $this->rawConverter;
    }
}
