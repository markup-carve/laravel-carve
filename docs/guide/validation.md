# Validation

The package provides a `ValidCarve` rule to validate that a string contains valid Carve markup.

## Basic Usage

```php
use MarkupCarve\LaravelCarve\Rules\ValidCarve;

$request->validate([
    'body' => ['required', 'string', new ValidCarve()],
]);
```

### In Form Requests

```php
use Illuminate\Foundation\Http\FormRequest;
use MarkupCarve\LaravelCarve\Rules\ValidCarve;

class StoreArticleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', new ValidCarve()],
        ];
    }
}
```

## Options

### Strict Mode

When enabled, parse warnings are also treated as validation errors.

```php
new ValidCarve(strict: true)
```

Default: `false`.

### Custom Message

Pass a custom message with the `{error}` placeholder to include the parse error detail:

```php
new ValidCarve(message: 'Please enter valid Carve markup: {error}')
```

The `:attribute` placeholder is still replaced by Laravel, so `The :attribute is not valid Carve markup: {error}` works by default.

## What Gets Validated

The rule checks:

1. **Syntax errors** — malformed Carve that cannot be parsed
2. **Parse warnings** (strict mode only) — valid but potentially problematic markup

### Note on Carve Parsing

Carve is designed to be very forgiving — most input will parse without errors. Unlike strict formats like JSON or YAML, Carve typically produces *some* output even from malformed input.

The validation is most useful for:

- Catching encoding issues
- Detecting truncated input
- Strict mode checking for warnings

## Next Steps

- [Blade Usage](blade-usage.md) — render Carve in views
- [Safe Mode](safe-mode.md) — protect against XSS
