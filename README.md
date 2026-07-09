# Laravel Carve

[![CI](https://github.com/markup-carve/laravel-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/laravel-carve/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/php-v/markup-carve/laravel-carve)](https://packagist.org/packages/markup-carve/laravel-carve)
[![License](https://img.shields.io/packagist/l/markup-carve/laravel-carve)](LICENSE)

[Carve](https://github.com/markup-carve/carve-php) markup language integration for Laravel — Blade directives, services, validation, and caching.

## Installation

```bash
composer require markup-carve/laravel-carve
```

The service provider and `Carve` facade alias are auto-discovered via Laravel's package discovery.

Optionally publish the config:

```bash
php artisan vendor:publish --tag=carve-config
```

## Usage

### Blade Directives

```blade
{{-- Safe by default - XSS protection enabled --}}
@carve($article->body)

{{-- For trusted content only - no XSS protection --}}
@carveRaw($trustedContent)

{{-- Plain text output (escaped) --}}
@carveText($article->body)
```

### Facade

```php
use MarkupCarve\LaravelCarve\Facades\Carve;

$html = Carve::toHtml($source);
$text = Carve::toText($source);
$raw  = Carve::toHtmlRaw($trustedSource);
```

### Dependency Injection

```php
use MarkupCarve\LaravelCarve\Service\CarveConverterInterface;
use MarkupCarve\LaravelCarve\Service\CarveManager;

class ArticleController
{
    public function __construct(
        private CarveConverterInterface $carve,
        private CarveManager $manager,
    ) {}

    public function show(Article $article): View
    {
        return view('article.show', [
            'html' => $this->carve->toHtml($article->body),
            'text' => $this->carve->toText($article->body),
            'docs' => $this->manager->toHtml($article->body, 'docs'),
        ]);
    }
}
```

## Configuration

```php
// config/carve.php
return [
    'converters' => [
        // Default has safe_mode: true (XSS protection enabled)
        'default' => [
            'safe_mode' => true,
        ],

        // For trusted content (admin, CMS)
        'trusted' => [
            'safe_mode' => false,
        ],
    ],
    'cache' => [
        'enabled' => false,
        'store' => null,
    ],
];
```

### Multiple Converter Profiles

Use different configurations for different contexts:

```blade
{{-- Default is safe --}}
@carve($comment->body)

{{-- Use named converter for trusted content --}}
{!! Carve::toHtml($article->body, 'trusted') !!}

{{-- Or use @carveRaw for quick trusted rendering --}}
@carveRaw($article->body)
```

### Safe Mode

Safe mode is *enabled by default* for XSS protection. Disable only for trusted content:

```php
'converters' => [
    'trusted' => [
        'safe_mode' => false,
    ],
],
```

### Extensions

Enable [carve-php extensions](https://github.com/markup-carve/carve-php) per converter:

```php
'converters' => [
    'default' => [
        'extensions' => [
            ['type' => 'autolink'],
            ['type' => 'smart_quotes'],
            [
                'type' => 'heading_permalinks',
                'symbol' => '#',
                'position' => 'after',
            ],
        ],
    ],
    'with_mentions' => [
        'extensions' => [
            [
                'type' => 'mentions',
                'user_url_template' => 'https://github.com/{username}',
            ],
            'table_of_contents',
        ],
    ],
],
```

Available extensions:

- `admonition` - Admonition blocks (note, tip, warning, danger, etc.)
- `autolink` - Auto-convert URLs to clickable links
- `code_group` - Transform code-group divs into tabbed interfaces
- `default_attributes` - Add default attributes to elements by type
- `external_links` - Configure external link behavior (target, rel)
- `frontmatter` - Parse YAML/TOML/JSON frontmatter blocks
- `heading_level_shift` - Shift heading levels up/down
- `heading_permalinks` - Add anchor links to headings
- `heading_reference` - Link to headings with `[text](#heading)` syntax
- `inline_footnotes` - Convert spans with class to inline footnotes
- `mentions` - Convert @username to profile links
- `mermaid` - Render Mermaid diagram code blocks
- `semantic_span` - Convert spans to `<kbd>`, `<dfn>`, `<abbr>` elements
- `smart_quotes` - Convert straight quotes to typographic quotes
- `table_of_contents` - Generate TOC from headings
- `tabs` - Tabbed content blocks (CSS or ARIA mode)
- `wikilinks` - Support `[[Page Name]]` wiki-style links

See [Extensions documentation](https://markup-carve.github.io/laravel-carve/extensions/) for detailed configuration options.

### Validation Rule

Validate that a field contains valid Carve markup:

```php
use MarkupCarve\LaravelCarve\Rules\ValidCarve;

$request->validate([
    'body' => ['required', 'string', new ValidCarve()],
]);
```

## Documentation

Full documentation: **[markup-carve.github.io/laravel-carve](https://markup-carve.github.io/laravel-carve/)**

- [Installation](https://markup-carve.github.io/laravel-carve/guide/installation)
- [Configuration](https://markup-carve.github.io/laravel-carve/guide/configuration)
- [Blade Usage](https://markup-carve.github.io/laravel-carve/guide/blade-usage)
- [Service Usage](https://markup-carve.github.io/laravel-carve/guide/service-usage)
- [Validation](https://markup-carve.github.io/laravel-carve/guide/validation)
- [Safe Mode](https://markup-carve.github.io/laravel-carve/guide/safe-mode)
- [Extensions](https://markup-carve.github.io/laravel-carve/extensions/)
- [Caching](https://markup-carve.github.io/laravel-carve/guide/caching)
- [Carve Syntax](https://markup-carve.github.io/laravel-carve/guide/carve-syntax)

## Demo Application

See the [laravel-carve-demo](https://github.com/markup-carve/laravel-carve-demo) for a complete example application.

## What is Carve?

[Carve](https://carve.net) is a modern light markup language created by John MacFarlane (author of CommonMark/Pandoc). It offers cleaner syntax and more features than Markdown while being easier to parse.

Learn more about Carve syntax at [carve.net](https://carve.net).

## Ecosystem

This package is part of the [Carve organization](https://github.com/markup-carve) -
the spec with its conformance corpus, three byte-identical reference
implementations (JS, PHP, Rust), editor plugins, and framework integrations.
See [awesome-carve](https://github.com/markup-carve/awesome-carve) for a curated
list of everything Carve.

