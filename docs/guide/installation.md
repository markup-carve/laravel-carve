# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 11.x, 12.x or 13.x

## Install via Composer

```bash
composer require markup-carve/laravel-carve
```

## Package Discovery

Laravel's package auto-discovery will automatically register:

- The `LaravelCarveServiceProvider`
- The `Carve` facade alias

No manual configuration is required to get started.

## Publishing the Config (optional)

If you want to customize the configuration, publish it to `config/carve.php`:

```bash
php artisan vendor:publish --tag=carve-config
```

## Verify Installation

Add a simple test in any Blade view:

```blade
@carve('*Hello* _world_!')
```

This should render:

```html
<p><strong>Hello</strong> <em>world</em>!</p>
```

## Next Steps

- [Configuration](configuration.md) — customize converter behavior
- [Blade Usage](blade-usage.md) — learn the available directives
- [Carve Syntax](carve-syntax.md) — quick reference for Carve markup
