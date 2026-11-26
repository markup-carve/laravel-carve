<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tiptap;

use RuntimeException;

/**
 * A Tiptap document saved through `AsCarveDocument` carries attributes the Carve bridge does not keep.
 */
class LossyTiptapDocument extends RuntimeException
{
    /**
     * @param string $key
     * @param array<array{path: string, type: string, attribute: string, value: mixed}> $ignored
     */
    public function __construct(public readonly string $key, public readonly array $ignored)
    {
        $first = $ignored[0] ?? null;
        parent::__construct(sprintf(
            'The Tiptap document in "%s" has %d attribute(s) Carve does not keep%s.',
            $key,
            count($ignored),
            $first === null ? '' : sprintf(', first "%s" on %s at %s', $first['attribute'], $first['type'], $first['path']),
        ));
    }
}
