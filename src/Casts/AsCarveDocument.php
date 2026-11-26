<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use MarkupCarve\LaravelCarve\Events\TiptapAttributesIgnored;
use MarkupCarve\LaravelCarve\Tiptap\CarveDocument;
use MarkupCarve\LaravelCarve\Tiptap\LossyTiptapDocument;
use MarkupCarve\LaravelCarve\Tiptap\TiptapConverter;
use Stringable;

/**
 * Stores a Tiptap (ProseMirror) JSON document unchanged and reads it back as a `CarveDocument`.
 *
 *     protected $casts = [
 *         'body' => AsCarveDocument::class,
 *         'comment' => AsCarveDocument::class.':comment',
 *     ];
 *
 *     $post->body->html(); // rendered through the "comment" profile
 *     $post->body->carve(); // canonical Carve source
 *
 * On assignment the document is converted once: a node with no Carve node throws, and attributes the bridge does not keep
 * raise the `carve.tiptap.on_loss` signal.
 *
 * @implements \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\MarkupCarve\LaravelCarve\Tiptap\CarveDocument|null, \MarkupCarve\LaravelCarve\Tiptap\CarveDocument|array<string, mixed>|\Stringable|string|null>
 */
class AsCarveDocument implements CastsAttributes
{
    /**
     * @var array
     */
    /**
     * @var array<string>
     */
    private const LOSS_SIGNALS = ['exception', 'event', 'ignore'];

    /**
     * Eloquent re-runs `set()` on a cached cast object at every save; the loss check must run only on assignment.
     */
    public bool $withoutObjectCaching = true;

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
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarveDocument
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('A Tiptap document column must contain JSON or null.');
        }

        return CarveDocument::from($value, $this->profile);
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
        if ($value === null) {
            return null;
        }
        $document = match (true) {
            $value instanceof CarveDocument, is_string($value) => CarveDocument::from($value),
            is_array($value) => CarveDocument::from(self::document($value)),
            $value instanceof Stringable => CarveDocument::from((string)$value),
            default => throw new InvalidArgumentException('A Tiptap document must be a CarveDocument, a decoded document, JSON or null.'),
        };

        $ignored = app(TiptapConverter::class)->ignoredAttributes($document);
        if ($ignored !== []) {
            $this->signalLoss($model, $key, $ignored);
        }

        return $document->toJson();
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function document(array $value): array
    {
        $document = [];
        foreach ($value as $name => $field) {
            $document[(string)$name] = $field;
        }

        return $document;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param array<array{path: string, type: string, attribute: string, value: mixed}> $ignored
     *
     * @throws \InvalidArgumentException
     * @throws \MarkupCarve\LaravelCarve\Tiptap\LossyTiptapDocument
     */
    private function signalLoss(Model $model, string $key, array $ignored): void
    {
        $signal = config('carve.tiptap.on_loss', 'exception');
        if (!in_array($signal, self::LOSS_SIGNALS, true)) {
            throw new InvalidArgumentException(sprintf('carve.tiptap.on_loss must be one of %s.', implode(', ', self::LOSS_SIGNALS)));
        }
        if ($signal === 'exception') {
            throw new LossyTiptapDocument($key, $ignored);
        }
        if ($signal === 'event') {
            event(new TiptapAttributesIgnored($model, $key, $ignored));
        }
    }
}
