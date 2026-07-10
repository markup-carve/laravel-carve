<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use InvalidArgumentException;

class CarveManager
{
    private ?CarveConverterInterface $rawConverter = null;

    /**
     * @param array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface> $converters
     */
    public function __construct(private array $converters)
    {
    }

    /**
     * Convert Carve markup to HTML using the named converter (or default).
     */
    public function toHtml(string $carve, string $converter = 'default'): string
    {
        return $this->getConverter($converter)->toHtml($carve);
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
    public function toText(string $carve, string $converter = 'default'): string
    {
        return $this->getConverter($converter)->toText($carve);
    }

    /**
     * Render Carve markup as Markdown using the named converter (or default).
     */
    public function toMarkdown(string $carve, string $converter = 'default'): string
    {
        return $this->getConverter($converter)->toMarkdown($carve);
    }

    /**
     * Render Carve markup as ANSI terminal output using the named converter
     * (or default).
     */
    public function toAnsi(string $carve, string $converter = 'default'): string
    {
        return $this->getConverter($converter)->toAnsi($carve);
    }

    /**
     * Get a named converter instance.
     */
    public function converter(string $name = 'default'): CarveConverterInterface
    {
        return $this->getConverter($name);
    }

    /**
     * @return array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface>
     */
    public function getConverters(): array
    {
        return $this->converters;
    }

    private function getConverter(string $name): CarveConverterInterface
    {
        if (!isset($this->converters[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Carve converter "%s" not found. Available converters: %s',
                $name,
                implode(', ', array_keys($this->converters)),
            ));
        }

        return $this->converters[$name];
    }

    private function getRawConverter(): CarveConverterInterface
    {
        if ($this->rawConverter === null) {
            $this->rawConverter = new CarveConverter(safeMode: false);
        }

        return $this->rawConverter;
    }
}
