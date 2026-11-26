<?php

declare(strict_types=1);

return [
    'default' => 'default',
    'views' => ['enabled' => true, 'converter' => null],

    // Absolute root for file-backed includes. Null leaves includes unresolved.
    'include_root' => null,

    'converters' => [
        'default' => [
            // Carve disables raw HTML by default and denies unsafe URL schemes.
            'raw_html' => false,
            'url_policy' => [],
            'profile' => 'full',
            'mode' => 'interactive',
            'smart_typography' => true,
            'soft_break_mode' => 'newline',
            'source_lines' => false,
            'sections' => true,
            'heading_ids' => 'keep-case',
            // Moves every heading down 0 to 5 levels, for a page whose layout writes the title as the <h1>.
            'heading_shift' => 0,
            'symbols' => [],
            'labels' => [],
            // Optional callable or invokable service returning text and a stable id for include requests.
            // 'include_resolver' => App\Carve\IncludeResolver::class,
            // ExtensionDefinition, ExtensionProvider, or the supported 'tabs' name.
            'extensions' => [],
            // OutputWriter instances for targets such as text, markdown and ansi.
            'writers' => [],
        ],
        'comment' => ['profile' => 'comment', 'raw_html' => false],
        'trusted' => ['raw_html' => true],
    ],

    // Tiptap (ProseMirror) JSON columns, see AsCarveDocument and TiptapConverter.
    'tiptap' => [
        // What a save does when the document has attributes Carve does not keep: 'exception', 'event' or 'ignore'.
        'on_loss' => 'exception',
        // The application's own nodes and marks, as the carve-php bridge's `mapping` option.
        'mapping' => [],
        // Converter profile for html(), plainText() and markdown(); null uses the default.
        'converter' => null,
    ],

    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => null,
        'prefix' => 'laravel_carve',
    ],
];
