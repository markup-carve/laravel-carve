# Safe Mode

Safe mode is *enabled by default* to provide XSS protection. This protects against malicious content in user comments, forum posts, or any input from external sources.

## Default Behavior

The `@carve` directive uses safe mode by default. For trusted content (admin/CMS), use `@carveRaw` or a named converter with `safe_mode: false`.

```blade
{{-- Safe by default - use for any content --}}
@carve($content)

{{-- Explicit raw - only for trusted content you control --}}
@carveRaw($article->body)
```

## When to Disable Safe Mode

| Content Source | Directive to Use |
|----------------|------------------|
| User comments | `@carve` (default) |
| Forum posts | `@carve` (default) |
| External API data | `@carve` (default) |
| User profile descriptions | `@carve` (default) |
| Admin/editor content | `@carveRaw` or named converter |
| CMS content (trusted editors) | `@carveRaw` or named converter |

**Rule of thumb:** Only use `@carveRaw` when you fully control and trust the content, or when it has been sanitized (e.g. via HTMLPurifier).

## What Safe Mode Does

Carve is safe by design; several protections are active *regardless* of safe mode:

- **Bare HTML tags are always literal text.** `<script>` in Carve source renders as the escaped text `&lt;script&gt;`, never as HTML. This is the key difference from Markdown, which passes raw HTML through by default.
- **Dangerous URLs are always sanitized.** `javascript:`, `data:` and other unsafe protocols in links are emptied in both modes.

Safe mode governs the one deliberate escape hatch - the **explicit raw-HTML passthrough** (```` ```=html ```` blocks and `` `...`{=html} `` inline):

- **Safe mode on** (default): passthrough content is escaped like any other text.
- **Safe mode off** (`@carveRaw` / `safe_mode: false`): passthrough content is emitted verbatim.

### Example: Raw HTML Passthrough

Input:

````carve
```=html
<script>alert(1)</script>
```
````

With `@carve` (safe mode, default):

```html
&lt;script&gt;alert(1)&lt;/script&gt;
```

With `@carveRaw` (no safe mode):

```html
<script>alert(1)</script>
```

### Example: Dangerous Link (sanitized in both modes)

```carve
[Click me](javascript:alert)
```

renders as `<p><a href="">Click me</a></p>` with `@carve` *and* with `@carveRaw` - URL sanitization is not affected by safe mode.

## Using Named Converters

For more control, define named converter profiles:

```php
// config/carve.php
return [
    'converters' => [
        'default' => [
            'safe_mode' => true,
        ],
        'trusted' => [
            'safe_mode' => false,
        ],
        'docs' => [
            'safe_mode' => false,
            'extensions' => [
                'table_of_contents',
                'heading_permalinks',
            ],
        ],
    ],
];
```

```blade
@carve($comment->text)
{!! Carve::toHtml($article->body, 'trusted') !!}
{!! Carve::toHtml($documentation->content, 'docs') !!}
```

## Security Recommendations

1. **Use the default** — `@carve` is safe by default; use it everywhere
2. **Explicit trust** — only use `@carveRaw` for content you control
3. **Validate before storing** — safe mode helps at render time, but validate input too with the `ValidCarve` rule
4. **Review trusted content** — even "trusted" content should be reviewed

## More Information

For the full security model (trust boundaries, the `=html` passthrough, per-engine switches), see the [Carve security documentation](https://markup-carve.github.io/carve/security) and the [markup-carve/carve-php README](https://github.com/markup-carve/carve-php).

## Next Steps

- [Configuration](configuration.md) - set up converter profiles
- [Service Usage](service-usage.md) - use in PHP code
