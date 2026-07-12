# Carve Syntax Quick Reference

Carve is a light markup language similar to Markdown but with cleaner, more consistent syntax. Its mnemonic: **the markup looks like its output**.

For comprehensive syntax documentation, see:

- [Carve documentation](https://markup-carve.github.io/carve/) - the canonical docs, including the [cheat sheet](https://markup-carve.github.io/carve/cheatsheet) and the [formal grammar](https://markup-carve.github.io/carve/grammar)
- [Coming from Markdown](https://markup-carve.github.io/carve/migrate-from-markdown) - task-oriented migration guide
- [markup-carve/carve-php](https://github.com/markup-carve/carve-php) - the PHP implementation this package wraps

## Key Differences from Markdown

If you're coming from Markdown, these are the main syntax changes:

| Feature | Markdown | Carve |
|---------|----------|------|
| Italic | `*text*` or `_text_` | `/text/` |
| Bold | `**text**` or `__text__` | `*text*` |
| Bold italic | `***text***` | `/*text*/` |
| Underline | (not standard) | `_text_` |
| Strikethrough | `~~text~~` (GFM) | `~text~` |
| Starting a list | may directly follow a paragraph | needs a blank line before the first item |
| Heading id | `# Title {#id}` (some flavors) | attribute line *above* the heading: `{#id}` |
| Table header | delimiter row `\|---\|` | `\|=` header cells (delimiter row also accepted) |

Fenced code blocks work exactly like Markdown: `` ```php `` with the language directly after the fence. (A space after the fence, the Djot style, is also accepted.)

The list rule deserves a callout: a `-` or `1.` line directly after a paragraph line does **not** start a list - it folds into the paragraph. Add a blank line before the first item:

```carve
Some paragraph text.

- now this is a list
- second item
```

## Quick Examples

### Inline Formatting

```carve
/italic/
*bold*
/*bold italic*/
_underline_
~strikethrough~
^superscript^
,subscript,
=highlight=
`inline code`
{+inserted+}
{-deleted-}
```

Bare delimiters work at word boundaries; use the brace form intraword, e.g. `H{,2,}O`.

### Links and Images

```carve
[Link text](https://example.com)
![Alt text](image.png)
<https://example.com>
```

### Lists

```carve
- Unordered item
- [ ] Task (unchecked)
- [x] Task (checked)

1. Ordered item
```

### Code Blocks

````carve
```php
$code = 'example';
```
````

### Attributes

Block attributes go on a standalone line *before* the block - including headings, where a trailing `{...}` is literal text:

```carve
{.warning}
This paragraph has a warning class.

{#custom-id}
# Heading
```

### Divs and Admonitions

```carve
::: note
This is a note admonition.
:::

::: my-class
Any other name renders as a div with that class.
:::
```

## Extensions

Beyond the core syntax, carve-php bundles optional extensions (mentions, wikilinks, table of contents, tabs, math, citations, and more). See the [Extensions](extensions.md) page for how to enable them in Laravel, and the [feature tier overview](https://markup-carve.github.io/carve/extensions#feature-tiers-quick-reference) in the canonical docs for what is core vs. opt-in.
