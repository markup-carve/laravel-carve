# Authoring with Carve 0.2

The Laravel facade renders and validates Carve 0.2 source. `Carve::fromHtml()` and `Carve::fromMarkdown()` import through the Carve importer. `fromMarkdown($source, 'gfm')` reads GitHub Flavored Markdown, so a `> [!TIP]` alert becomes a `::: tip` admonition; `carve:convert --from=gfm` does the same for a file.

Use the [migration guide](migration.md) to update existing source and settings. It links the 0.2 upgrade tool and explains SILENT source changes.
