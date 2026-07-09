# Caching

Caching can significantly improve performance when rendering the same content repeatedly. The package integrates with Laravel's cache component and can use any registered store.

## Enable Caching

```php
// config/carve.php
return [
    'cache' => [
        'enabled' => true,
        'store' => null, // null = default store from config/cache.php
    ],
];
```

## How It Works

1. Content is hashed using xxHash (fast, collision-resistant)
2. Rendered HTML is stored in the chosen cache store
3. Subsequent renders of identical content return cached HTML
4. Cache keys are prefixed with `laravel_carve_html_`

## Cache Stores

Any Laravel cache store defined in `config/cache.php` can be used:

```php
'cache' => [
    'enabled' => true,
    'store' => 'redis',
],
```

### Common Store Configurations

**Redis (recommended for production):**

```php
// config/cache.php
'stores' => [
    'carve' => [
        'driver' => 'redis',
        'connection' => 'cache',
    ],
],
```

```php
// config/carve.php
'cache' => [
    'enabled' => true,
    'store' => 'carve',
],
```

**File (development):**

```php
'stores' => [
    'carve' => [
        'driver' => 'file',
        'path' => storage_path('framework/cache/carve'),
    ],
],
```

**Array (testing):**

```php
'cache' => [
    'enabled' => true,
    'store' => 'array',
],
```

## Cache Invalidation

The cache is keyed by content hash, so:

- **Changed content** automatically gets a new cache entry
- **Old entries** never expire unless the store has a TTL configured
- **Manual clearing** via Artisan:

```bash
# Clear a specific store
php artisan cache:clear carve

# Clear everything
php artisan cache:clear
```

## When to Use Caching

**Good candidates:**

- Static content (about pages, documentation)
- Blog posts that don't change often
- Repeated rendering of the same content

**Skip caching for:**

- User-specific content with variables
- Rapidly changing content
- Very short content (overhead > benefit)

## Performance Considerations

| Scenario | Recommendation |
|----------|----------------|
| Blog with 100 posts | Enable caching with Redis |
| User comments | Usually skip (too many unique entries) |
| Documentation site | Enable with file cache |
| Real-time editor preview | Disable caching |

## Next Steps

- [Configuration](configuration.md) — full configuration reference
- [Service Usage](service-usage.md) — using the converter in code
