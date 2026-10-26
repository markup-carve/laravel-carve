# Laravel Carve

[![CI](https://github.com/markup-carve/laravel-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/laravel-carve/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/php-v/markup-carve/laravel-carve)](https://packagist.org/packages/markup-carve/laravel-carve)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/packagist/l/markup-carve/laravel-carve)](LICENSE)

Laravel integration for Carve through carve-php 0.1.14 or later. It provides Blade rendering, services, validation, and caching. Read the [Carve documentation](https://markup-carve.github.io/carve/) for the language syntax.

## Install

```sh
composer require markup-carve/laravel-carve
```

## Usage

```blade
@carve('# Hello')
```

## Documentation

- [Carve documentation](https://markup-carve.github.io/carve/)
- [Documentation index](docs/README.md)
- [Laravel guide](docs/guide/index.md)
