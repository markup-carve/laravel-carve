# Configuration

## Default Configuration

The package works out of the box with sensible defaults. Safe mode is enabled by default for security.

## Full Configuration Reference

```php
// config/carve.php
return [
    'converters' => [
        'default' => [
            'safe_mode' => true,        // XSS protection (enabled by default)
            'mode' => 'interactive',    // 'interactive' or 'static'
            'soft_break_mode' => null,  // null, 'newline', 'space' or 'br'
            'xhtml' => false,           // XHTML-compatible output
            'extensions' => [],
        ],

        // Add custom profiles as needed
        'trusted' => [
            'safe_mode' => false,
        ],
    ],
    'cache' => [
        'enabled' => false,
        'store' => null, // null = default cache store
    ],
];
```

## Converter Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `safe_mode` | bool | `true` | Escape the explicit `=html` raw passthrough - disable only for trusted content (see [Safe Mode](safe-mode.md)) |
| `mode` | string | `'interactive'` | Render mode: `interactive` or `static` (see [Render Mode](#render-mode)) |
| `soft_break_mode` | string\|null | `null` | How to render soft breaks: `newline`, `space`, or `br` |
| `xhtml` | bool | `false` | Output XHTML-compatible markup (self-closing tags) |
| `extensions` | array | `[]` | Carve extensions to enable for this profile (see [Extensions](extensions.md)) |

## Converter Profiles

You can define multiple converter profiles for different contexts. Each profile is resolved as its own `CarveConverter` instance.

### Example: Default Safe + Trusted Converter

```php
'converters' => [
    // Default is safe
    'default' => [
        'safe_mode' => true,
    ],

    // For trusted admin/editor content
    'trusted' => [
        'safe_mode' => false,
    ],

    // For documentation with extensions
    'docs' => [
        'safe_mode' => false,
        'extensions' => [
            'table_of_contents',
            'heading_permalinks',
        ],
    ],
],
```

### Using Profiles in Blade

```blade
{{-- Uses 'default' profile (safe mode) --}}
@carve($comment->text)

{{-- Uses 'trusted' profile (no safe mode) --}}
{!! Carve::toHtml($article->body, 'trusted') !!}

{{-- Quick way for trusted content --}}
@carveRaw($article->body)
```

### Using Profiles in Services

```php
use MarkupCarve\LaravelCarve\Service\CarveManager;

class ContentService
{
    public function __construct(
        private CarveManager $carve,
    ) {}

    public function renderComment(string $text): string
    {
        return $this->carve->toHtml($text); // default profile
    }

    public function renderArticle(string $text): string
    {
        return $this->carve->toHtml($text, 'trusted');
    }
}
```

## Container Bindings

The package registers the following bindings:

| Binding | Description |
|---------|-------------|
| `MarkupCarve\LaravelCarve\Service\CarveManager` | Multi-profile manager (singleton) |
| `carve` | Alias for `CarveManager` |
| `MarkupCarve\LaravelCarve\Service\CarveConverterInterface` | Default converter instance |

## Render Mode

Each profile accepts a `mode`:

```php
'print' => [
    'safe_mode' => true,
    'mode' => 'static',
],
```

- `interactive` (default) - full HTML for browsers, including script-backed
  extensions (tabs, diagrams).
- `static` - the spec's graceful-degradation rules for script-free targets
  (print, PDF, email): disclosures render `open`, tab panels appear in
  sequence with their labels, diagram sources are preserved. Content and
  structure always survive; only the interaction drops.

## Next Steps

- [Extensions](extensions.md) - enable and configure Carve extensions
- [Safe Mode](safe-mode.md) - understand XSS protection
- [Caching](caching.md) - improve performance with caching
