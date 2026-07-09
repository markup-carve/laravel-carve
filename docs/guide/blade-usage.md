# Blade Usage

## Directives

### `@carve` Directive

Converts Carve markup to HTML. Safe mode is enabled by default, protecting against XSS.

```blade
@carve($article->body)
```

The directive outputs raw HTML — the surrounding `<?php echo ?>` is emitted for you.

### `@carveRaw` Directive

Converts Carve markup to HTML *without* safe mode. Use only for trusted content.

```blade
{{-- Only use for content you fully control --}}
@carveRaw($trustedArticle->body)
```

This bypasses XSS protection — dangerous URLs (`javascript:`, `data:`) and raw HTML blocks are preserved. Never use with user-generated content.

### `@carveText` Directive

Converts Carve markup to plain text. The result is HTML-escaped via Laravel's `e()` helper. Useful for:

- Search indexing
- Meta descriptions
- Email plain text fallbacks
- Previews/excerpts

```blade
<meta name="description" content="@carveText(Str::limit($article->body, 160))">
```

## Facade

The `Carve` facade exposes the same functionality for inline use:

```blade
{!! Carve::toHtml($content) !!}
{!! Carve::toHtml($content, 'docs') !!}
{!! Carve::toHtmlRaw($trustedContent) !!}
{{ Carve::toText($content) }}
```

Remember: `{{ }}` escapes HTML. For `toHtml()` / `toHtmlRaw()`, use `{!! !!}` or the directives.

## Common Patterns

### Conditional Rendering

```blade
@if($article->body)
    <div class="content">
        @carve($article->body)
    </div>
@endif
```

### With Default Value

```blade
@carve($article->body ?? '')
```

### Excerpt with Fallback

```blade
@php($excerpt = $article->excerpt ?? Str::limit(Carve::toText($article->body), 200))
<p class="excerpt">{{ $excerpt }}</p>
```

### User-Generated Content

The default `@carve` directive is safe for user content:

```blade
{{-- Safe - XSS protection enabled by default --}}
@carve($comment->text)
```

### Trusted CMS Content

For content from trusted sources (admin, editors):

```blade
{{-- Quick way - use @carveRaw --}}
@carveRaw($article->body)

{{-- Or use a named converter with extensions --}}
{!! Carve::toHtml($article->body, 'docs') !!}
```

### Inline Content

For short inline content like titles or labels:

```blade
<h1>@carve($article->title)</h1>
```

Note: This wraps the content in `<p>` tags. If you need truly inline output, strip the wrapper:

```blade
<h1>{!! Str::of(Carve::toHtml($article->title))->replaceMatches('#^<p>|</p>$#', '')->trim() !!}</h1>
```

## Next Steps

- [Service Usage](service-usage.md) - use the converter in PHP code
- [Safe Mode](safe-mode.md) - understand XSS protection
