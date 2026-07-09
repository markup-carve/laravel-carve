# Extensions

The package supports all [carve-php extensions](https://github.com/markup-carve/carve-php). Extensions are configured per converter profile in `config/carve.php`.

## Configuration

```php
// config/carve.php
return [
    'converters' => [
        'default' => [
            'extensions' => [
                ['type' => 'autolink'],
                ['type' => 'smart_quotes'],
            ],
        ],
    ],
];
```

Shorthand string form is also accepted when no options are needed:

```php
'extensions' => [
    'autolink',
    'smart_quotes',
    'semantic_span',
],
```

## Available Extensions

### admonition

Creates styled admonition blocks (callouts) for notes, warnings, tips, etc.

```php
[
    'type' => 'admonition',
    'types' => ['note', 'tip', 'warning', 'danger', 'info', 'success'],
    'default_title' => true,
    'title_tag' => 'p',
    'title_class' => 'admonition-title',
    'container_class' => 'admonition',
    'icons' => true,
    'icon_class' => 'admonition-icon',
]
```

Usage in Carve:

```
::: note
This is a note.
:::

::: warning Custom Title
This is a warning with a custom title.
:::
```

### autolink

Automatically converts bare URLs and email addresses to clickable links.

```php
[
    'type' => 'autolink',
    'allowed_schemes' => ['https', 'http', 'mailto'],
]
```

### code_group

Transforms code-group divs into tabbed code block interfaces. Labels are extracted from the language hint using `[Label]` suffix syntax.

```php
[
    'type' => 'code_group',
    'wrapper_class' => 'code-group',
    'panel_class' => 'code-group-panel',
    'label_class' => 'code-group-label',
    'radio_class' => 'code-group-radio',
    'id_prefix' => 'codegroup',
]
```

Usage in Carve:

~~~
::: code-group

``` php [PHP]
echo "Hello";
```

``` js [JavaScript]
console.log("Hello");
```

:::
~~~

### default_attributes

Adds default attributes to elements by type.

```php
[
    'type' => 'default_attributes',
    'defaults' => [
        'image' => [
            'loading' => 'lazy',
            'decoding' => 'async',
        ],
        'table' => [
            'class' => 'table table-striped',
        ],
    ],
]
```

### external_links

Configures behavior for external links (adds `target="_blank"` and security attributes).

```php
[
    'type' => 'external_links',
    'internal_hosts' => ['example.com', 'localhost'],
    'target' => '_blank',
    'rel' => 'noopener noreferrer',
    'nofollow' => false,
]
```

### frontmatter

Parses YAML, TOML, or JSON frontmatter blocks at the start of documents.

```php
[
    'type' => 'frontmatter',
    'default_format' => 'yaml',
    'render_as_comment' => true,
]
```

### heading_level_shift

Shifts all heading levels by a specified amount. Useful when embedding content.

```php
[
    'type' => 'heading_level_shift',
    'shift' => 1, // 1-5
]
```

### heading_permalinks

Adds anchor links to headings for easy linking.

```php
[
    'type' => 'heading_permalinks',
    'symbol' => '#',
    'position' => 'after',
    'class' => 'heading-anchor',
    'aria_label' => 'Permalink',
]
```

### heading_reference

Creates links to headings using `[text](#heading-id)` syntax.

```php
[
    'type' => 'heading_reference',
    'css_class' => 'heading-ref',
]
```

### inline_footnotes

Converts spans with a specific class to inline footnotes.

```php
[
    'type' => 'inline_footnotes',
    'css_class' => 'fn',
]
```

### mentions

Converts @username references to profile links.

```php
[
    'type' => 'mentions',
    'user_url_template' => 'https://github.com/{username}',
    'user_class' => 'mention',
]
```

### mermaid

Renders code blocks with `mermaid` language as Mermaid diagrams.

```php
[
    'type' => 'mermaid',
    'tag' => 'pre',
    'css_class' => 'mermaid',
    'wrap_in_figure' => false,
    'figure_class' => 'mermaid-figure',
]
```

### semantic_span

Converts span attributes to semantic HTML5 elements.

```php
['type' => 'semantic_span']
```

Usage in Carve:

```
[Ctrl+C]{kbd}                                    → <kbd>Ctrl+C</kbd>
[API]{dfn="Application Programming Interface"}  → <dfn title="...">API</dfn>
[HTML]{abbr="HyperText Markup Language"}         → <abbr title="...">HTML</abbr>
```

### smart_quotes

Converts straight quotes to typographic (curly) quotes.

```php
[
    'type' => 'smart_quotes',
    'locale' => 'en',
]
```

### table_of_contents

Generates a table of contents from headings. Use `{toc}` placeholder in your document.

```php
[
    'type' => 'table_of_contents',
    'min_level' => 1,
    'max_level' => 6,
    'toc_class' => 'toc',
]
```

### tabs

Creates tabbed content blocks. Supports CSS-only or ARIA modes.

```php
[
    'type' => 'tabs',
    'mode' => 'css',
    'wrapper_class' => 'tabs',
    'tab_class' => 'tabs-panel',
    'label_class' => 'tabs-label',
    'radio_class' => 'tabs-radio',
    'id_prefix' => 'tabset',
]
```

### wikilinks

Supports `[[Page Name]]` wiki-style links.

```php
[
    'type' => 'wikilinks',
    'url_template' => '/wiki/{page}',
    'link_class' => 'wiki-link',
]
```

## Using Multiple Converters

Define different converter profiles for different use cases:

```php
'converters' => [
    'default' => [
        'extensions' => [
            'autolink',
            'smart_quotes',
        ],
    ],

    'documentation' => [
        'extensions' => [
            'heading_permalinks',
            'table_of_contents',
        ],
    ],

    'user_content' => [
        'safe_mode' => true,
        'extensions' => [
            [
                'type' => 'mentions',
                'user_url_template' => '/users/{username}',
            ],
        ],
    ],
],
```

Use in Blade:

```blade
@carve($article->body)
{!! Carve::toHtml($docs->content, 'documentation') !!}
{!! Carve::toHtml($comment->text, 'user_content') !!}
```

Or inject the manager and pick the converter:

```php
public function __construct(
    private CarveManager $carve,
) {}

public function renderDocs(string $content): string
{
    return $this->carve->converter('documentation')->toHtml($content);
}
```
