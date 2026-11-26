<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tiptap;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * A Tiptap (ProseMirror) document as the editor saved it. The JSON is kept unchanged; the Carve views go through
 * the carve-php ProseMirror bridge in `TiptapConverter`.
 *
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
final class CarveDocument implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * @param array<string, mixed> $document
     * @param string|null $converter
     * @param string|null $json The JSON it was read from, stored again as it was: decoding loses `{}` versus `[]`.
     */
    private function __construct(
        private readonly array $document,
        private readonly ?string $converter = null,
        private readonly ?string $json = null,
    ) {
    }

    /**
     * @param array<string, mixed>|string $document The editor's
     *   `getJSON()`, decoded or as JSON.
     * @param string|null $converter The converter profile for `html()`, `plainText()` and `markdown()`.
     *
     * @throws \InvalidArgumentException For invalid JSON or a root that is not a ProseMirror `doc`.
     */
    public static function from(self|array|string $document, ?string $converter = null): self
    {
        if ($document instanceof self) {
            return $converter === null ? $document : new self($document->document, $converter, $document->json);
        }
        $json = null;
        if (is_string($document)) {
            $json = $document;
            try {
                $document = json_decode($document, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('A Tiptap document must be valid JSON: ' . $exception->getMessage(), 0, $exception);
            }
        }
        if (!is_array($document) || ($document['type'] ?? null) !== 'doc') {
            throw new InvalidArgumentException('A Tiptap document must be a ProseMirror node of type "doc".');
        }

        /** @var array<string, mixed> $document */
        return new self($document, $converter, $json);
    }

    /**
     * The Tiptap document whose Carve source is `$source`, as the editor loads it.
     */
    public static function fromCarve(string $source, ?string $converter = null): self
    {
        return new self(app(TiptapConverter::class)->fromCarve($source), $converter);
    }

    public function converter(): ?string
    {
        return $this->converter;
    }

    /**
     * Canonical Carve source of the document.
     */
    public function carve(): string
    {
        return app(TiptapConverter::class)->toCarve($this);
    }

    public function html(): string
    {
        return app(TiptapConverter::class)->html($this, $this->converter);
    }

    public function plainText(): string
    {
        return app(TiptapConverter::class)->plainText($this, $this->converter);
    }

    public function markdown(): string
    {
        return app(TiptapConverter::class)->markdown($this, $this->converter);
    }

    /**
     * @return array<\MarkupCarve\Carve\Diagnostics\Diagnostic>
     */
    public function lint(): array
    {
        return app(TiptapConverter::class)->lint($this);
    }

    /**
     * @return array<array{path: string, type: string, attribute: string, value: mixed}>
     */
    public function ignoredAttributes(): array
    {
        return app(TiptapConverter::class)->ignoredAttributes($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->document;
    }

    /**
     * @param int $options
     */
    public function toJson($options = 0): string
    {
        if ($this->json !== null && $options === 0) {
            return $this->json;
        }

        return json_encode($this->document, $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->document;
    }
}
