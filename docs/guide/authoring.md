# Authoring with Carve 0.2

The Laravel facade renders and validates Carve 0.2 source. `Carve::fromHtml()` and `Carve::fromMarkdown()` import through the Carve importer. `fromMarkdown($source)` reads GitHub Flavored Markdown by default, so tables convert and a `> [!TIP]` alert becomes a `::: tip` admonition; `carve:convert` does the same for a `.md` file or with `--from=gfm`. `fromMarkdown($source, 'markdown')` and `--from=markdown` read strict CommonMark.

Use the [migration guide](migration.md) to update existing source and settings. It links the 0.2 upgrade tool and explains SILENT source changes.
