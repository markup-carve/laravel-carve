# Extensions

The 0.2 extension host is provisional. A converter profile's `extensions` list takes Carve ExtensionDefinition or ExtensionProvider objects, or the names below. A name is a string (`'details'`) or an array with a `type` key and options (`['type' => 'table_of_contents', 'max_level' => 3]`). Unknown names and options fail with an `InvalidArgumentException`.

## Names

Options are the parameters of the carve-php factory each name calls, written in snake case: `css_class` sets `$cssClass`. Closure parameters cannot be set from configuration; pass the engine object instead.

| Name | carve-php factory | Options |
|---|---|---|
| `abc`, `chart`, `d2`, `graphviz`, `mermaid`, `vega_lite`, `wavedrom` | `FencedRender::<preset>()` | `css_class`, `tag`, `wrap_in_figure`, `figure_class`, `label` |
| `fenced_render` | `FencedRender::bundle()` | `language` (required), `css_class`, `tag`, `content_mode`, `wrap_in_figure`, `figure_class`, `label` |
| `plantuml` | host fenced renderer for `plantuml` and `puml` | `css_class` |
| `autolink` | `Navigation::autolink()` | `allowed_schemes` |
| `external_links` | `Navigation::externalLinks()` | `target`, `rel`, `nofollow`, `internal_hosts` |
| `heading_numbers` | `Navigation::headingNumbers()` | `min_level`, `label`, `crossref` |
| `heading_permalinks` | `Navigation::headingPermalinks()` | `symbol`, `css_class`, `aria_label`, `levels`, `prepend`, `show_on_hover`, `copy_to_clipboard` |
| `heading_reference` | `Navigation::headingReference()` | `css_class` |
| `link_policy` | `Navigation::linkPolicy()` | `allowed_schemes`, `denied_schemes`, `allowed_domains`, `denied_domains`, `allow_external`, `allow_internal`, `rel`, `base_host` |
| `table_of_contents` | `Navigation::tableOfContents()` | `min_level`, `max_level`, `list_type`, `css_class`, `position`, `collapsible`, `summary`, `open` |
| `toc_placement` | `Navigation::tocPlacement()` | none |
| `citations` | `Citations::bundle()` | `mode`, `bibliography` |
| `code_callouts`, `details`, `glossary`, `list_table`, `semantic_span`, `spoiler` | `<Extension>::bundle()` | none |
| `code_group` | `CodeGroup::bundle()` | `mode`, `wrapper_class`, `panel_class`, `label_class`, `radio_class`, `id_prefix`, `group_label` |
| `color_swatch` | `ColorSwatch::bundle()` | `position`, `shape`, `tint`, `reveal` |
| `default_attributes` | `Transforms::defaultAttributes()` | `defaults` (required) |
| `img_fence` | `ImgFence::definition()` with its renderer | `language` (default `img` and `image`) |
| `index_terms` | `IndexTerms::bundle()` | `backref_label` |
| `math_block` | `MathBlock::bundle()` | `language` |
| `smart_quotes` | `SmartQuotes::bundle()` | `locale`, `open_double_quote`, `close_double_quote`, `open_single_quote`, `close_single_quote` |
| `tabs` | the standard Carve tabs definitions | `mode` (`css` or `aria`) |
| `wikilinks` | `Wikilinks::bundle()` | `url_template` (`{page}` is the slug), `css_class`, `new_window` |

`ExtensionFactory::types()` returns every name.

Mentions and tags are core 0.2 options, not extensions: set the profile's `social` setting. See the [migration guide](migration.md#extensions) and the [Carve extension guide](https://github.com/markup-carve/carve-php/blob/main/docs/README.md#extensions-provisional).
