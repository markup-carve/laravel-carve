# Getting Started

Carve markup language integration for Laravel applications.

## What is Carve?

[Carve](https://github.com/markup-carve/carve) is a lightweight markup language for structured documents, with clear, consistent syntax:

- **Clear syntax** - consistent rules with fewer edge cases
- **Document features** - footnotes, definition lists, task lists, math, highlights, and more
- **Extensible design** - built for customization

## Features

This package provides:

- **Blade directives** — `@carve`, `@carveRaw`, `@carveText`
- **Facade** — `Carve::toHtml()`, `Carve::toText()`, `Carve::toHtmlRaw()`
- **Service injection** — `CarveConverterInterface` and `CarveManager` for controllers and services
- **Validation** — `ValidCarve` rule for request validation
- **Multiple profiles** — different configurations for different contexts (e.g. user content vs. admin content)
- **Safe mode** — XSS protection for untrusted input (enabled by default)
- **Caching** — optional caching of rendered output via any Laravel cache store
- **Plain text** — extract plain text for search indexing or previews

## Quick Start

```bash
composer require markup-carve/laravel-carve
```

```blade
{{-- In your Blade views --}}
@carve($article->body)
```

```php
// In your services
public function __construct(
    private CarveConverterInterface $carve,
) {}

public function render(string $content): string
{
    return $this->carve->toHtml($content);
}
```

## Requirements

- PHP 8.2 or higher
- Laravel 11.x, 12.x or 13.x

## Demo Application

See the [Laravel Carve Demo](https://github.com/markup-carve/laravel-carve-demo) for a complete example application.
