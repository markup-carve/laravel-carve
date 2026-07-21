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
$md   = Carve::toMarkdown($source);
$ansi = Carve::toAnsi($source); // terminal output
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
- `citations` - Bracketed citations with an in-document bibliography (numbered or author-date)
- `code_callouts` - Numbered callout markers on fenced-code lines with a bound explanation list
- `code_group` - Transform code-group divs into tabbed interfaces
- `color_swatch` - Inline color swatches for CSS color tokens via the `color` role
- `default_attributes` - Add default attributes to elements by type
- `details` - Render `::: details` blocks as native `<details>`/`<summary>` widgets
- `external_links` - Configure external link behavior (target, rel)
- `fenced_render` - Emit fenced blocks of a chosen language as client-rendered hydration elements
- `frontmatter` - Parse YAML/TOML/JSON frontmatter blocks
- `glossary` - Glossary definition lists with linked term references
- `heading_level_shift` - Shift heading levels up/down
- `heading_numbers` - Auto-number sections and rewrite heading cross-references
- `heading_permalinks` - Add anchor links to headings
- `heading_reference` - Link to headings with `[text](#heading)` syntax
- `index` - Collect `:index[term]` markers into a sorted index block
- `inline_footnotes` - Convert spans with class to inline footnotes
- `list_table` - Author tables as nested lists (`::: list-table`) with block content in cells
- `math_block` - Render `math` fenced code blocks as display math
- `mentions` - Convert @username to profile links
- `mermaid` - Render Mermaid diagram code blocks
- `plantuml` - Render PlantUML/`puml` diagram code blocks (needs a client renderer; see below)
- `semantic_span` - Convert spans to `<kbd>`, `<dfn>`, `<abbr>` elements
- `smart_quotes` - Convert straight quotes to typographic quotes
- `spoiler` - Hidden spoiler content revealed on interaction
- `tab_normalize` - Expand tabs in code content to spaces at render time
- `table_of_contents` - Generate TOC from headings
- `tabs` - Tabbed content blocks (CSS or ARIA mode)
- `toc_placement` - Render the TOC exactly where a `::: toc` block appears
- `wikilinks` - Support `[[Page Name]]` wiki-style links

See [Extensions documentation](https://markup-carve.github.io/laravel-carve/extensions/) for detailed configuration options.

### Client-side diagram rendering

The diagram extensions emit a `<pre class="LANG">` hydration element; the browser
turns it into a picture. Mermaid, WaveDrom, Vega-Lite and Chart each render once
you load their library. **PlantUML, D2 and Graphviz have no browser library** and
render via a [Kroki](https://kroki.io) server. The shared helper from
[`@markup-carve/carve-grammars`](https://github.com/markup-carve/carve-grammars)
does this in a few lines:

```js
import { renderKrokiDiagrams } from '@markup-carve/carve-grammars/diagrams/kroki'

// after the rendered Carve HTML is on the page
await renderKrokiDiagrams(document.querySelector('.carve-content'))
```

For a build-time / SSR pipeline, render diagrams server-side instead so no client
JS ships.

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

A full runnable demo app lives at [laravel-carve-demo](https://github.com/markup-carve/laravel-carve-demo): the Blade directives, facade and service usage, form validation, safe-mode comparison, static render mode, plain text extraction and the extension set.

[![laravel-carve demo](https://raw.githubusercontent.com/markup-carve/laravel-carve-demo/main/docs/screenshots/blade-directive.png)](https://github.com/markup-carve/laravel-carve-demo)

## What is Carve?

[Carve](https://github.com/markup-carve/carve) is a post-Markdown lightweight markup language. It builds on the foundations of [Djot](https://github.com/jgm/djot), John MacFarlane's post-Markdown project, and offers cleaner syntax and more features than Markdown while being easier to parse.

Learn more about Carve syntax at [github.com/markup-carve/carve](https://github.com/markup-carve/carve).

## Ecosystem

This package is part of the [Carve organization](https://github.com/markup-carve) -
the spec with its conformance corpus, three byte-identical reference
implementations (JS, PHP, Rust), editor plugins, and framework integrations.
See [awesome-carve](https://github.com/markup-carve/awesome-carve) for a curated
list of everything Carve.

