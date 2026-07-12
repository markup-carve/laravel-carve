<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use MarkupCarve\Carve\CarveConverter;
use MarkupCarve\Carve\Node\Document;

interface CarveConverterInterface
{
    /**
     * Get the underlying carve-php converter, e.g. to add extensions programmatically.
     */
    public function getConverter(): CarveConverter;

    /**
     * Convert Carve markup to HTML.
     */
    public function toHtml(string $carve): string;

    /**
     * Convert Carve markup to plain text.
     */
    public function toText(string $carve): string;

    /**
     * Render Carve markup as Markdown.
     */
    public function toMarkdown(string $carve): string;

    /**
     * Render Carve markup as ANSI terminal output.
     */
    public function toAnsi(string $carve): string;

    /**
     * Parse Carve markup into an AST document.
     */
    public function parse(string $carve): Document;
}
