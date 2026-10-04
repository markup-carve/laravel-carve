<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\RenderedCarve;
use Stringable;

/**
 * Stores Carve source, reads it back rendered.
 *
 *     protected $casts = [
 *         'body' => AsCarve::class,
 *         'comment' => AsCarve::class.':comment',
 *     ];
 *
 *     {{ $post->body }} // rendered HTML
 *     $post->body->source // the stored markup
 *     $post->body->toc()
 *
 * @implements \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\MarkupCarve\LaravelCarve\RenderedCarve|null, \MarkupCarve\LaravelCarve\RenderedCarve|\Stringable|string|null>
 */
class AsCarve implements CastsAttributes
{
    public function __construct(private readonly ?string $profile = null)
    {
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @throws \InvalidArgumentException
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RenderedCarve
    {
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException('A Carve attribute must contain string source or null.');
        }

        return $value === null ? null : Carve::render($value, $this->profile);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     *
     * @throws \InvalidArgumentException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof RenderedCarve => $value->source,
            is_string($value), $value instanceof Stringable => (string)$value,
            default => throw new InvalidArgumentException('A Carve attribute must contain string source or null.'),
        };
    }
}
