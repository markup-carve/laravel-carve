# Tiptap columns

Many apps keep editor content as Tiptap JSON in a database column. `AsCarveDocument` and `TiptapConverter` read that JSON through the carve-php ProseMirror bridge (`ProseMirrorToCarve` and `ProseMirrorRenderer`), so the column can be rendered, indexed, exported and linted as Carve without a Node runtime. The JSON itself stays as the editor saved it.

The bridge reads the schema of the Carve Tiptap kit in carve-grammars and the content a stock Tiptap editor makes. The carve-php [ProseMirror guide](https://github.com/markup-carve/carve-php/blob/main/docs/prosemirror.md) describes the schema and the `mapping` option.

## A model with a Tiptap column

```php
use Illuminate\Database\Eloquent\Model;
use MarkupCarve\LaravelCarve\Casts\AsCarveDocument;

class Post extends Model
{
    protected $casts = [
        'body' => AsCarveDocument::class,
        // Exports of this column go through the "comment" converter profile.
        'reply' => AsCarveDocument::class . ':comment',
    ];
}
```

The column is a `json` or `text` column. Saving accepts what the editor sends:

```php
$post->body = $request->input('body');           // the editor's getJSON(), decoded
$post->body = $request->string('body_json');     // or as JSON
$post->body = CarveDocument::fromCarve($source); // or Carve source, loaded as the editor would load it
$post->save();
```

Reading gives a `MarkupCarve\LaravelCarve\Tiptap\CarveDocument`, or null for a null column:

```php
$post->body->toArray();     // the document, for the editor's setContent()
$post->body->carve();       // canonical Carve source
$post->body->html();        // HTML through the cast's converter profile
$post->body->plainText();   // for a search index or an AI prompt
$post->body->markdown();    // for an export
$post->body->lint();        // Carve lint findings
```

`CarveDocument` is JSON-serializable, so returning a model from a controller sends the editor JSON unchanged. A JSON string is stored byte for byte as it arrived; a decoded array is stored with `json_encode()`.

## Saves that would lose data

Each time a document is assigned to the attribute, the cast converts it once. Two things stop a silent loss:

- A node or mark with no Carve node, such as a `mention` node from an extension the bridge does not know, throws `InvalidArgumentException`. Map it with `carve.tiptap.mapping`.
- An attribute the bridge does not keep, such as `textAlign` on a paragraph, raises the signal in `carve.tiptap.on_loss`:

| Value | Effect |
|---|---|
| `exception` (default) | throws `MarkupCarve\LaravelCarve\Tiptap\LossyTiptapDocument`; `$exception->ignored` lists each attribute with its JSON pointer |
| `event` | keeps the document and dispatches `MarkupCarve\LaravelCarve\Events\TiptapAttributesIgnored` with the model, the attribute key and the list |
| `ignore` | keeps the document |

The signal fires on assignment, before `save()`, so the exception leaves the attribute as it was and the event does not mean the row was written. A kept document is stored unchanged; the signal tells you that its Carve exports drop those attributes. Reading a stored lossy document and saving the model for another change does not fire it again.

```php
use Illuminate\Support\Facades\Event;
use MarkupCarve\LaravelCarve\Events\TiptapAttributesIgnored;

Event::listen(function (TiptapAttributesIgnored $event): void {
    logger()->warning('Tiptap attributes Carve does not keep', [
        'model' => $event->model::class,
        'key' => $event->key,
        'ignored' => $event->ignored, // [['path' => '/content/0', 'type' => 'paragraph', 'attribute' => 'textAlign', 'value' => 'center']]
    ]);
});
```

## The service

`MarkupCarve\LaravelCarve\Tiptap\TiptapConverter` is a container singleton (also bound as `carve.tiptap`). Every method takes a `CarveDocument`, a decoded document or its JSON. `Carve` below is this package's facade, and `$source` is `toCarve($doc)`:

| Method | Returns |
|---|---|
| `toCarve($doc)` | canonical Carve source, as `ProseMirrorToCarve::convert()` writes it |
| `fromCarve($source)` | the Tiptap document of Carve source, as `ProseMirrorRenderer::renderSource()` builds it |
| `ignoredAttributes($doc)` | the bridge's `ignoredAttributes()` after converting `$doc` |
| `plainText($doc, ?$profile)` | the plain text of the Carve source, as `Carve::toText($source, $profile)` gives it |
| `markdown($doc, ?$profile)` | the Markdown export, as `Carve::toMarkdown($source, $profile)` gives it |
| `html($doc, ?$profile)` | HTML, as `Carve::toHtml($source, $profile)` gives it |
| `lint($doc)` | the findings of `Carve::lint($source)` |
| `pointerFor($doc, $path)` | the JSON pointer of the editor node a finding's `path` came from |

The exports go through the named converter profile, so raw HTML, URL policy and `heading_shift` apply as they do to a Carve column. `pointerFor()` turns a lint finding into an editor position:

```php
$tiptap = app(TiptapConverter::class);
foreach ($tiptap->lint($post->body) as $finding) {
    $pointer = $tiptap->pointerFor($post->body, (string)$finding->path); // "/content/1"
}
```

## Configuration

```php
'tiptap' => [
    // 'exception', 'event' or 'ignore'
    'on_loss' => 'exception',
    // The application's own nodes and marks, as the bridge's `mapping` option.
    'mapping' => [],
    // Converter profile for html(), plainText() and markdown(); null uses the default.
    'converter' => null,
],
```

`mapping` goes to both bridge directions. A placeholder node and a colored text mark:

```php
'mapping' => [
    'nodes' => ['placeholderToken' => ['as' => 'span', 'class' => 'placeholder', 'attributes' => ['name']]],
    'marks' => ['textStyle' => ['as' => 'span', 'class' => 'text-style', 'attributes' => ['color']]],
],
```

::: warning Closures and config caching
A mapping entry made of closures (`toTree`, `fromTree`) works too, but `php artisan config:cache` cannot cache a config file with closures. Give such a mapping in a service provider instead, by binding `TiptapConverter` yourself: `new TiptapConverter($app->make(CarveManager::class), $mapping, $profile)`.
:::
