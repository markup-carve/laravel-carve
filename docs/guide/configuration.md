# Configuration

Publish config/carve.php with the package's carve-config publish tag. A converter profile passes validated options to Carve2. The default disables raw HTML and uses the secure URL policy.

    'converters' => [
        'default' => [
            'profile' => 'full',
            'mode' => 'interactive',
            'raw_html' => false,
            'url_policy' => [],
            'extensions' => [],
            'writers' => [],
        ],
    ],

The [migration guide](migration-02.md#rendering-and-profiles) lists every 0.2 key and its mapping. include_root is an absolute trusted root for file rendering. cache.enabled, cache.store, cache.ttl, and cache.prefix control Laravel caching.
