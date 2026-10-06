---
name: carve-development
description: Render, store, validate and author Carve markup in Laravel with markup-carve/laravel-carve. Use when working with .crv files, Carve source fields, @carve Blade directives, the Carve facade, AsCarve casts, ValidCarve rules, carve:* Artisan commands or config/carve.php.
---

# Carve Development

## When to use this skill

Use it when a task touches Carve markup: rendering it in Blade, storing it on a model, validating user input, converting from Markdown or HTML, configuring converter profiles, or writing `.crv` documents.

## Carve syntax essentials

Carve looks like Markdown but differs in ways that silently change output:

| Feature | Markdown | Carve |
|---------|----------|-------|
| Italic | `*text*` | `/text/` |
| Bold | `**text**` | `*text*` |
| Bold italic | `***text***` | `/*text*/` |
| Underline | n/a | `_text_` |
| Strikethrough | `~~text~~` | `~text~` |
| Highlight | n/a | `=text=` |
| Superscript / subscript | n/a | `{^x^}` / `{,x,}` |
| Heading id | `# Title {#id}` | `{#id}` on the line above the heading |
| Table header | `\|---\|` row | `\|=` header cells |

- A `-` or `1.` line directly after a paragraph folds into the paragraph. Put a blank line before the first list item.
- Block attributes (`{.class}`, `{#id}`) go on a standalone line before the block.
- `::: note` ... `:::` makes an admonition; any other name makes a div with that class.
- Fenced code blocks work like Markdown.

Full reference: https://markup-carve.github.io/carve/cheatsheet

## Converter profiles

`config/carve.php` defines named profiles under `converters`. `carve.default` picks the one used when no name is passed.

- `default`: safe mode on. Use for general user content.
- `comment`: strict safe mode plus the `comment` preset (restricted features). Use for untrusted short-form input.
- `trusted`: safe mode off, raw HTML allowed. Only for content from trusted authors.

```php
'converters' => [
    'reviews' => [
        'safe_mode' => 'strict',
        'preset' => 'comment',          // full, article, comment, minimal
        'on_disallowed' => 'to_text',   // or strip, error
        'extensions' => [
            ['type' => 'autolink'],
            ['type' => 'heading_permalinks', 'symbol' => '#', 'position' => 'after'],
            'table_of_contents',
        ],
    ],
],
```

Unknown extension names or options are rejected. `symbols` values are inserted as raw HTML, so never fill them from user input.

## Rendering

Blade:

```blade
@carve($post->body)                 {{-- safe, default profile --}}
@carve($comment->body, 'comment')   {{-- named profile --}}
@carveText($post->body)             {{-- escaped plain text --}}
@carveRaw($trusted)                 {{-- no XSS protection: trusted only --}}

<x-carve :source="$post->body" profile="comment" class="article" />
```

Facade and helpers:

```php
use MarkupCarve\LaravelCarve\Facades\Carve;

Carve::toHtml($source, 'comment');
Carve::toText($source);
Carve::toMarkdown($source);
Carve::toAnsi($source);

$page = Carve::render($source);   // RenderedCarve
$page->html;
$page->toc();
$page->meta('title');             // frontmatter; YAML needs symfony/yaml

Str::carve($source);
str($source)->carveText()->limit(160);
```

Inject `MarkupCarve\LaravelCarve\Service\CarveManager` (or `CarveConverterInterface` for the default profile) instead of the facade in classes.

`.crv` files under `resources/views` render through `view('docs.intro')`. They are static: view variables are not interpolated.

## Storing on models

```php
use MarkupCarve\LaravelCarve\Casts\AsCarve;

protected $casts = ['body' => AsCarve::class.':comment'];
```

The cast stores source and returns a `RenderedCarve`; `{{ $post->body }}` prints its HTML. Preset length limits also apply when reading, so check existing rows before switching a column to a stricter preset.

## Validation

```php
use MarkupCarve\LaravelCarve\Rules\ValidCarve;

$request->validate([
    'body' => ['required', 'string', ValidCarve::preset('comment')->maxLength(5000)],
    'document' => ['string', (new ValidCarve())->strict()->lint()],
]);
```

- `preset()` rejects markup the preset forbids. Match the preset of the profile that renders the field.
- `strict()` rejects parse warnings such as unresolved references.
- `lint()` rejects likely mistakes such as Markdown `**bold**`.

## Imports and commands

```php
Carve::fromMarkdown($markdown);   // returns Carve source
Carve::fromHtml($html);
Carve::lint($source);             // list of LintWarning
```

```sh
php artisan carve:render page.crv --format=html --profile=default
php artisan carve:convert page.md --output=page.crv
php artisan carve:lint resources/views
```

## File includes

String rendering and Blade directives never read files. To expand includes, set an absolute `carve.include_root` and call `Carve::toHtmlFile($path)`, `Carve::toHtmlFileWithReport($path)` or `Carve::renderFile($path)`. Paths outside the root are refused.

## Caching

`carve.cache` takes `enabled`, `store`, `ttl` and `prefix`. An extension holding a closure disables caching unless its profile sets `cache_version`; bump it whenever the closure's output changes. `Carve::extend($profile, $callback)` customizes the engine; calling `getConverter()` disables caching for that profile.

## Common pitfalls

- Writing Markdown emphasis (`**bold**`, `*italic*`) in Carve source.
- Rendering user input with `@carveRaw` or the `trusted` profile.
- Validating with one preset and rendering with a less restrictive profile.
- Expecting `@carve` or `.crv` views to resolve includes or Blade variables.
