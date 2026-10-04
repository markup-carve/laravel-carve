::: v-pre

# Authoring in Laravel

Store Carve source and render it with a named converter. Existing
`carve.converters` configuration and Blade directives remain supported.
`carve.default` selects the converter used when no name is supplied.

## Safety and validation

The shipped `default` converter keeps safe mode enabled. `comment` combines
strict safe mode with the engine's comment preset, which restricts document
features. `trusted` permits explicit raw HTML and should only render source
from trusted authors.

```php
// config/carve.php
'converters' => [
    'reviews' => [
        'safe_mode' => 'strict',
        'preset' => 'comment',
        'on_disallowed' => 'to_text',
    ],
],
```

Presets are `full`, `article`, `comment` and `minimal`. Disallowed markup can
become text, be stripped, or raise an error with `on_disallowed => 'error'`.
A validation preset rejects forbidden markup before saving it:

```php
use MarkupCarve\LaravelCarve\Rules\ValidCarve;

$request->validate([
    'body' => ['required', ValidCarve::preset('comment')->maxLength(5000)],
    'document' => ['string', (new ValidCarve())->strict()->lint()],
]);
```

`strict()` rejects parse warnings, including unresolved references. `lint()`
rejects likely authoring mistakes such as Markdown's `**bold**`. Length limits
count characters. The existing `new ValidCarve(strict: true, message: '...')`
constructor remains supported.

## Rendered values and model casts

```php
use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\Casts\AsCarve;

$page = Carve::render($source);
$page->html;
$page->toc();
$page->meta('title');

// On an Eloquent model:
protected $casts = ['body' => AsCarve::class . ':comment'];
```

`AsCarve` stores source and returns a `RenderedCarve` object. Assigning a
rendered object stores its `source`, preserving the original markup. Blade
prints `{{ $post->body }}` as HTML under the cast's selected converter.
JSON serialization includes source, HTML, headings and frontmatter. Preset
length limits also apply when reading stored values; validate before saving
and check existing rows before switching their cast to a restrictive preset.

JSON frontmatter is parsed without extra packages. Install `symfony/yaml` to
parse YAML through `meta()`. Unsupported or invalid metadata returns an empty
array. `renderFile()` also returns redacted include warnings, dependencies and
the suppressed-warning count. File caching checks dependency contents before
reusing a result.

## Components and document views

```blade
<x-carve :source="$post->body" profile="comment" class="article" />

<x-carve>
    # Hello {{ $name }}
</x-carve>
```

The component preserves a rendered value’s HTML unless an explicit profile
is supplied. Source strings use the selected converter. Slot indentation is removed. Rendered content is never
compiled as Blade code. Dynamic values in a slot can introduce Carve syntax;
validate them or pass a complete source string when you need explicit control.
With an unsafe profile, slot interpolation can introduce raw HTML even when
the Blade variable uses `{{ }}` escaping.

A file such as `resources/views/docs/intro.crv` can be rendered with
`view('docs.intro')`. Carve views are static documents; view variables are not
interpolated. Set `carve.views.enabled` to `false` to disable registration.
Set `carve.views.converter` to select their converter. When `carve.include_root`
is configured, Carve view files must also be inside that root.

## Imports, lint and commands

```php
Carve::fromMarkdown($markdown);
Carve::fromHtml($html);
Carve::lint($source);
Str::carve($source);
str($source)->carveText()->limit(160);
```

Imports produce Carve source. Render imported content with a safe converter.

```sh
php artisan carve:render page.crv --format=html --profile=default
php artisan carve:render page.crv --format=text --output=page.txt
php artisan carve:convert page.md --output=page.crv
php artisan carve:lint resources/views
```

Rendering supports HTML, text, Markdown and ANSI. All file formats expand
includes when `carve.include_root` is configured. The entry document and
included files must remain inside that root. String rendering never reads
includes. Lint exits unsuccessfully for findings or missing paths.

## Extensions and cache configuration

Extensions accept shorthand names, configuration arrays, class names or
`ExtensionInterface` instances. Snake-case options map to constructor arguments;
legacy aliases such as `toc_class` remain accepted. Converter configuration
rejects unknown extension names and options.

`carve.cache` accepts `enabled`, `store`, `ttl` in seconds and `prefix`.
Cache signatures include engine version, rendering settings and extension
state. Serializable extensions can be cached automatically. An extension
containing a closure disables caching unless its converter supplies a
`cache_version` that identifies the callback's output behavior. Change that
version whenever the callback changes. Configured wiki-link URL templates also
use a callback and need `cache_version` to enable caching.

`Carve::extend('default', $callback)` customizes the underlying engine.
`Carve::flush()` rebuilds lazily configured converters. Calling
`getConverter()` exposes a mutable engine and disables caching for that
converter, including customization through `extend()`, to prevent stale output.

## Attribution

The value object, cast, component, view engine and commands adapt MIT-licensed
work from [Jefferson Gonçalves's Laravel integration](https://github.com/jeffersongoncalves/laravel-carve).
The license notice is retained in `LICENSES/`.

:::
