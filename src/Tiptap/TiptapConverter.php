<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tiptap;

use MarkupCarve\Carve\ProseMirror\ProseMirrorRenderer;
use MarkupCarve\Carve\ProseMirror\ProseMirrorToCarve;
use MarkupCarve\LaravelCarve\Service\CarveManager;

/**
 * Tiptap (ProseMirror) JSON to Carve and back through the carve-php ProseMirror bridge, and the exports of such a
 * document. Each export converts the document to Carve source and hands it to the named converter profile.
 */
class TiptapConverter
{
    /**
     * @param \MarkupCarve\LaravelCarve\Service\CarveManager $manager
     * @param array<string, mixed> $mapping The application's own nodes and marks, as the bridge's `mapping` option.
     * @param string|null $converter The default converter profile for the exports.
     */
    public function __construct(
        private readonly CarveManager $manager,
        private readonly array $mapping = [],
        private readonly ?string $converter = null,
    ) {
    }

    /**
     * Canonical Carve source of the document.
     *
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     */
    public function toCarve(CarveDocument|array|string $document): string
    {
        return $this->bridge()->convert(CarveDocument::from($document)->toArray());
    }

    /**
     * The Tiptap document of Carve source, for the editor's `setContent()`.
     *
     * @return array<string, mixed>
     */
    public function fromCarve(string $source): array
    {
        return $this->renderer()->renderSource($source);
    }

    /**
     * The attributes the bridge does not keep, each with the JSON pointer of its node or mark.
     *
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     *
     * @return array<array{path: string, type: string, attribute: string, value: mixed}>
     */
    public function ignoredAttributes(CarveDocument|array|string $document): array
    {
        return $this->convert($document)[1];
    }

    /**
     * The Carve source and the ignored attributes of one conversion.
     *
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     *
     * @return array{0: string, 1: array<array{path: string, type: string, attribute: string, value: mixed}>}
     */
    public function convert(CarveDocument|array|string $document): array
    {
        $bridge = $this->bridge();
        $source = $bridge->convert(CarveDocument::from($document)->toArray());

        return [$source, $bridge->ignoredAttributes()];
    }

    /**
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     * @param string|null $converter
     */
    public function plainText(CarveDocument|array|string $document, ?string $converter = null): string
    {
        return $this->manager->toText($this->toCarve($document), $converter ?? $this->converter);
    }

    /**
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     * @param string|null $converter
     */
    public function markdown(CarveDocument|array|string $document, ?string $converter = null): string
    {
        return $this->manager->toMarkdown($this->toCarve($document), $converter ?? $this->converter);
    }

    /**
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     * @param string|null $converter
     */
    public function html(CarveDocument|array|string $document, ?string $converter = null): string
    {
        return $this->manager->toHtml($this->toCarve($document), $converter ?? $this->converter);
    }

    /**
     * Lint findings of the document's Carve source. `pointerFor()` gives the editor node of a finding's `path`.
     *
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     *
     * @return array<\MarkupCarve\Carve\Diagnostics\Diagnostic>
     */
    public function lint(CarveDocument|array|string $document): array
    {
        return $this->manager->lint($this->toCarve($document));
    }

    /**
     * The JSON pointer of the Tiptap node a tree path (such as a lint finding's `path`) came from.
     *
     * @param \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|string $document
     * @param string $treePath
     */
    public function pointerFor(CarveDocument|array|string $document, string $treePath): ?string
    {
        $bridge = $this->bridge();
        $bridge->toTree(CarveDocument::from($document)->toArray());

        return $bridge->pointerFor($treePath);
    }

    private function bridge(): ProseMirrorToCarve
    {
        return new ProseMirrorToCarve(['mapping' => $this->mapping]);
    }

    private function renderer(): ProseMirrorRenderer
    {
        return new ProseMirrorRenderer(['mapping' => $this->mapping]);
    }
}
