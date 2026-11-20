# Laravel Carve 0.2 migration

This branch is for the unreleased Carve engine. Composer uses the engine's next2 commit through a VCS repository and a commit-pinned dev-next2 constraint. The package needs PHP 8.2, DOM, and Laravel 12 or 13. There is no registry package to install for this branch.

## Source documents

Run the Carve 0.2 upgrade tool from carve-js next2 on each existing source document, then run its 0.2 lint and format checks. Review the HTML for every SILENT upgrade row. The [specification upgrade guide](https://github.com/markup-carve/carve/blob/next2/spec/0.2/UPGRADING.md) explains changes in block interruption, lists, tables, inline spans, breaks, and raw HTML. This package has no tracked Carve source files.

For a new source, separate a heading after paragraph text with a blank line. Use native header cells in tables and braced alignment. A 0.2 example:

    = Overview

    A *strong* point.

    |= Name |= Value |
    | One | 1 |

## Rendering and profiles

The Laravel facade, Blade component, directives, casts, and static Carve views still call the named converter. The converter now wraps the public Carve static API. Direct callers construct it with an option array:

    $converter = new CarveConverter(options: [
        'profile' => 'article',
        'rawHtml' => false,
        'urlPolicy' => [],
        'mode' => 'static',
    ]);
    $html = $converter->toHtml($source);
    $tree = $converter->parse($source);

The parsed tree is a Carve Node. It is no longer a 0.1 Document. The adapter's options method returns the current Carve option map; setOption changes one option and invalidates its cache. CarveManager::extend passes the Laravel adapter to its callback. Use setOption or registerWriter there.

The config file uses snake case for settings and maps them to the Carve option names:

| Config key | Carve option | Default |
| --- | --- | --- |
| profile | profile | full |
| mode | mode | interactive |
| raw_html | rawHtml | false |
| url_policy | urlPolicy | empty policy |
| smart_typography | smartTypography | true |
| source_lines | sourceLines | false |
| sections | sections | true |
| heading_ids | headingIds | keep-case |
| strict_losses | strictLosses | false |
| symbols | symbols | empty map |
| labels | labels | empty map |
| social | social | unset |
| extensions | extensions | empty list |

The 0.1 safe_mode, preset, on_disallowed, soft_break_mode, and xhtml keys have no direct 0.2 equivalent. Replace safe_mode with raw_html and url_policy, preset with profile, and review the other old settings against the 0.2 option table. Carve validates option names and values. The default policy keeps raw HTML off and denies unsafe URL schemes. The trusted raw HTML helper enables only rawHtml; it does not loosen the URL policy.

## Includes and diagnostics

Set include_root to an existing absolute directory. File methods pass FileResolver::forRoot with the file identity to Carve. String methods do not read local files. The result includes Carve diagnostics in warnings and a dependency report. The dependencies report uses relative identities under the configured root. Included files are rechecked before cached file output is reused.

CarveManager::lint and the lint command call Carve::lint. Findings are Diagnostic objects with code, message, severity, channel, byte offsets, and file. Use code instead of the old rule property. The validation rule uses Carve profile losses and the 0.2 lint catalog.

## Formatting and import

CarveManager::format and the format command call Carve::format. HTML input import calls Carve::importDocument with from set to html. Markdown input import has no Carve API yet; fromMarkdown and the convert command return a clear unavailable error for that input.

## Output writers

The text, Markdown, and ANSI methods and render command formats remain public. They parse a Carve tree and call Carve::writeTo through a profile writer registry. The engine currently registers only html and carve. Until a text, markdown, or ansi OutputWriter is registered, the corresponding method reports that the writer is not available yet.

A host may provide an OutputWriter instance in a profile's writers list or call registerWriter on the adapter. Each writer receives a resolved Carve Node and an options array. The standard text, Markdown, and ANSI writers are tracked in carve-php issue 5235.

## Extensions

A profile's extensions list accepts Carve ExtensionDefinition or ExtensionProvider objects and the names in the [extension guide](extensions.md#names). Most 0.1 names map to a carve-php 0.2 extension of the same name; options use the 0.2 factory parameters (for example `table_of_contents` takes `css_class`, not `toc_class`, and `heading_permalinks` takes `prepend`, not `position`). The 0.1 extension classes cannot run on a Carve tree and fail explicitly. `mentions` became the core `social` setting, and `frontmatter` and `admonition` are core 0.2 syntax with no registration. Several former extensions are now core Carve 0.2 syntax; review source and settings before removing a registration. Document transform and build-time or inline renderer hooks are still pending the engine's provisional extension API.

## Cache and result data

RenderedCarve still supplies HTML, heading metadata, frontmatter, warnings, dependencies, and source. The metadata is read from Carve nodes; the heading HTML in the table of contents is rendered with the active Carve options, so inline formatting survives. Cache entries are separated by option map and engine pin; mutating an option or registering a writer disables caching for that adapter. File cache entries are checked against dependency content hashes, including targets named by absolute paths inside the include root.
