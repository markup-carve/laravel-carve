<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched by `AsCarveDocument` when `carve.tiptap.on_loss` is `event` and a saved document carries attributes
 * the Carve bridge does not keep. The document is stored unchanged.
 */
class TiptapAttributesIgnored
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param array<array{path: string, type: string, attribute: string, value: mixed}> $ignored
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $key,
        public readonly array $ignored,
    ) {
    }
}
