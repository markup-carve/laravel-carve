<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Converter Profiles
    |--------------------------------------------------------------------------
    |
    | Define one or more converter profiles. Each profile is resolved as its
    | own CarveConverter instance. The "default" profile is used when no name
    | is explicitly passed to the facade, Blade directives or the manager.
    |
    */

    'converters' => [

        'default' => [
            // XSS protection — disable only for trusted content
            'safe_mode' => true,

            // Render mode: 'interactive' (default) or 'static'. Static mode
            // applies the spec's graceful-degradation rules for print, PDF,
            // email and other script-free targets.
            'mode' => 'interactive',

            // How to render soft breaks: null, "newline", "space" or "br"
            'soft_break_mode' => null,

            // Output XHTML-compatible self-closing tags
            'xhtml' => false,

            // Carve extensions to enable for this profile. See docs/extensions.md.
            'extensions' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Enable caching of rendered HTML output. Cached entries are keyed by a
    | hash of the source content so unchanged input is returned from cache.
    |
    | The "store" key refers to a cache store defined in config/cache.php.
    | Use null to use the application's default cache store.
    |
    */

    'cache' => [
        'enabled' => false,
        'store' => null,
    ],

];
