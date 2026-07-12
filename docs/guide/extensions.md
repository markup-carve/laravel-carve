# Extensions

carve-php bundles a set of optional extensions - from autolinking and mentions to tabs, math and citations. This package lets you enable them per converter profile in `config/carve.php`, by name or with options.

For what each extension does (syntax, output, options), see the [carve-php extension documentation](https://github.com/markup-carve/carve-php/blob/main/docs/extensions.md). For which features are core vs. opt-in across Carve implementations, see the [feature tier overview](https://markup-carve.github.io/carve/extensions#feature-tiers-quick-reference).

## Enabling Extensions

Each entry in a profile's `extensions` array is either a shorthand string or an array with a `type` key plus options. Only options you set are forwarded - everything else keeps the library defaults.

```php
// config/carve.php
'converters' => [
    'docs' => [
        'safe_mode' => false,
        'extensions' => [
            // Shorthand - library defaults
            'table_of_contents',
            'heading_permalinks',

            // With options
            [
                'type' => 'mentions',
                'mention_url' => 'https://github.com/{name}',
            ],
            [
                'type' => 'wikilinks',
                'url_template' => '/wiki/{page}',
            ],
        ],
    ],
],
```

## Supported Types

| Type | Extension |
|------|-----------|
| `admonition` | Admonition callouts (`::: note`, `::: warning`, …) |
| `autolink` | Turn bare URLs into links |
| `citations` | `[@key]` citations with a generated reference list |
| `code_callouts` | `<1>` markers in code blocks |
| `code_group` | Tabbed code groups from labeled fences |
| `color_swatch` | Inline color swatches for color literals |
| `default_attributes` | Attach default attributes to elements |
| `details` | Collapsible `<details>` blocks |
| `external_links` | `target`/`rel`/`nofollow` handling for external links |
| `fenced_render` | Render fenced blocks via a client library (diagrams, charts) |
| `frontmatter` | YAML/TOML/JSON frontmatter handling |
| `glossary` | Glossary term definitions and references |
| `heading_level_shift` | Shift all heading levels by an offset |
| `heading_numbers` | Numbered headings |
| `heading_permalinks` | Anchor links on headings |
| `heading_reference` | Wiki-style `[Heading Name][]` links to headings |
| `index` | Back-of-book index generation |
| `inline_footnotes` | `^[inline]` footnotes |
| `list_table` | Tables written as nested lists |
| `math_block` | `$$`-style display math blocks |
| `mentions` | `@user` mentions and `#tag` tags |
| `mermaid` | Shorthand for `fenced_render` with `language: mermaid` |
| `semantic_span` | Semantic span classes |
| `smart_quotes` | Locale-aware typographic quotes |
| `spoiler` | Spoiler blocks |
| `tab_normalize` | Normalize tab indentation |
| `table_of_contents` | Generated table of contents |
| `tabs` | Tabbed content panels |
| `toc_placement` | Place the TOC via a `::: toc` marker |
| `wikilinks` | `[[Page Name]]` wiki links |

Unknown types are silently skipped, so double-check the spelling if an extension does not seem to take effect.

## Beyond the Named Types

A few carve-php extensions have no config shorthand yet (for example `AsciiHeadingIdsExtension`, `LowercaseHeadingIdsExtension`, `PlusBulletExtension`). You can still add any extension instance programmatically on the underlying converter:

```php
use MarkupCarve\Carve\Extension\AsciiHeadingIdsExtension;
use MarkupCarve\LaravelCarve\Service\CarveManager;

$converter = app(CarveManager::class)->converter('docs');
$converter->getConverter()->addExtension(new AsciiHeadingIdsExtension());
```

## Next Steps

- [Configuration](configuration.md) - profiles and converter options
- [Safe Mode](safe-mode.md) - what safe mode governs
