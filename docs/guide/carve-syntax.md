# Carve Syntax Quick Reference

Carve is a light markup language similar to Markdown but with cleaner, more consistent syntax.

For comprehensive syntax documentation, see:

- [Official Carve specification](https://htmlpreview.github.io/?https://github.com/jgm/carve/blob/master/doc/syntax.html)
- [markup-carve/carve-php documentation](https://markup-carve.github.io/carve-php/guide/syntax) — includes PHP-specific extensions

## Key Differences from Markdown

If you're coming from Markdown, these are the main syntax changes:

| Feature | Markdown | Carve |
|---------|----------|------|
| Emphasis (italic) | `*text*` or `_text_` | `_text_` |
| Strong (bold) | `**text**` or `__text__` | `*text*` |
| Code fence | ` ```lang ` | ` ``` lang ` (space required) |

## Quick Examples

### Inline Formatting

```carve
_emphasis_ (italic)
*strong* (bold)
_*strong emphasis*_ (bold italic)
`inline code`
~subscript~
^superscript^
{+insert+}
{-delete-}
{=highlight=}
```

### Links and Images

```carve
[Link text](https://example.com)
![Alt text](image.png)
```

### Lists

```carve
- Unordered item
- [ ] Task (unchecked)
- [x] Task (checked)

1. Ordered item
```

### Code Blocks

Note the space between ``` and the language name:

````carve
``` php
$code = 'example';
```
````

### Attributes

```carve
{.warning}
This paragraph has a warning class.

# Heading {#custom-id}
```

### Divs

```carve
::: note
This is a div with class "note".
:::
```

## PHP-Specific Extensions

The [markup-carve/carve-php](https://github.com/markup-carve/carve-php) library includes several extensions beyond standard Carve:

- Fenced comments (`%%%`)
- Abbreviations (`*[ABBR]: definition`)
- Table rowspan/colspan
- Boolean attributes

See the [full syntax documentation](https://markup-carve.github.io/carve-php/guide/syntax) for details.
