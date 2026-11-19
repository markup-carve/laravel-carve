<?php

declare(strict_types=1);

return [
    'default' => 'default',
    'views' => ['enabled' => true, 'converter' => null],

    // Absolute root for file-backed includes. Null leaves includes unresolved.
    'include_root' => null,

    'converters' => [
        'default' => [
            // Carve2 disables raw HTML by default and denies unsafe URL schemes.
            'raw_html' => false,
            'url_policy' => [],
            'profile' => 'full',
            'mode' => 'interactive',
            'smart_typography' => true,
            'source_lines' => false,
            'sections' => true,
            'heading_ids' => 'keep-case',
            'symbols' => [],
            'labels' => [],
            // ExtensionDefinition, ExtensionProvider, or the supported 'tabs' name.
            'extensions' => [],
            // OutputWriter instances for targets such as text, markdown and ansi.
            'writers' => [],
        ],
        'comment' => ['profile' => 'comment', 'raw_html' => false],
        'trusted' => ['raw_html' => true],
    ],

    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => null,
        'prefix' => 'laravel_carve',
    ],
];
