# Laravel Carve 0.2 migration

This branch is for the unreleased Carve2 engine. Composer uses the engine's next2 commit through a VCS repository and a commit-pinned dev-next2 constraint. The package needs PHP 8.2, DOM, and Laravel 12 or 13. There is no registry package to install for this branch.

## Source documents

Run the Carve 0.2 upgrade tool from carve-js next2 on each existing source document, then run its 0.2 lint and format checks. Review the HTML for every SILENT upgrade row. The [specification upgrade guide](https://github.com/markup-carve/carve/blob/next2/spec/0.2/UPGRADING.md) explains changes in block interruption, lists, tables, inline spans, breaks, and raw HTML. This package has no tracked Carve source files.

For a new source, separate a heading after paragraph text with a blank line. Use native header cells in tables and braced alignment. A 0.2 example:

    = Overview

    A *strong* point.

    |= Name |= Value |
    | One | 1 |

## Rendering and profiles

The Laravel facade, Blade component, directives, casts, and static Carve views still call the named converter. The converter now wraps the public Carve2 static API. Direct callers construct it with an option array:

    $converter = new CarveConverter(options: [
        'profile' => 'article',
        'rawHtml' => false,
        'urlPolicy' => [],
        'mode' => 'static',
    ]);
    $html = $converter->toHtml($source);
    $tree = $converter->parse($source);

The parsed tree is a Carve2 Node. It is no longer a 0.1 Document. The adapter's options method returns the current Carve2 option map; setOption changes one option and invalidates its cache. CarveManager::extend passes the Laravel adapter to its callback. Use setOption or registerWriter there.

The config file uses snake case for settings and maps them to the Carve2 option names:

| Config key | Carve2 option | Default |
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

The 0.1 safe_mode, preset, on_disallowed, soft_break_mode, and xhtml keys have no direct 0.2 equivalent. Replace safe_mode with raw_html and url_policy, preset with profile, and review the other old settings against the 0.2 option table. Carve2 validates option names and values. The default policy keeps raw HTML off and denies unsafe URL schemes. The trusted raw HTML helper enables only rawHtml; it does not loosen the URL policy.

## Includes and diagnostics

Set include_root to an existing absolute directory. File methods pass FileResolver::forRoot with the file identity to Carve2. String methods do not read local files. The result includes Carve2 diagnostics in warnings and a dependency report. The dependencies report uses relative identities under the configured root. Included files are rechecked before cached file output is reused.

CarveManager::lint and the lint command call Carve2::lint. Findings are Diagnostic objects with code, message, severity, channel, byte offsets, and file. Use code instead of the old rule property. The validation rule uses Carve2 profile losses and the 0.2 lint catalog.

## Formatting and import

CarveManager::format and the format command call Carve2::format. HTML input import calls Carve2::importDocument with from set to html. Markdown input import has no Carve2 API yet; fromMarkdown and the convert command return a clear unavailable error for that input.

## Output writers

The text, Markdown, and ANSI methods and render command formats remain public. They parse a Carve2 tree and call Carve2::writeTo through a profile writer registry. The engine currently registers only html and carve. Until a text, markdown, or ansi OutputWriter is registered, the corresponding method reports that the writer is not available yet.

A host may provide an OutputWriter instance in a profile's writers list or call registerWriter on the adapter. Each writer receives a resolved Carve2 Node and an options array. The standard text, Markdown, and ANSI writers are tracked in carve-php issue 5235.

## Extensions

A profile's extensions list accepts Carve2 ExtensionDefinition or ExtensionProvider objects. The tabs shorthand registers the standard Carve2 tabs definitions. The plantuml shorthand registers fenced renderers for both `plantuml` and `puml`, with an optional `css_class` for a single CSS class. Other old 0.1 extension names and classes cannot be passed into the 0.2 engine. They fail explicitly so no 0.1 behavior enters the 0.2 path. Several former extensions are now core Carve 0.2 syntax; review source and settings before removing a registration. Document transform and build-time or inline renderer hooks are still pending the engine's provisional extension API.

## Cache and result data

RenderedCarve still supplies HTML, heading metadata, frontmatter, warnings, dependencies, and source. The metadata is read from Carve2 nodes; the heading HTML in the table of contents is rendered with the active Carve2 options, so inline formatting survives. Cache entries are separated by option map and engine pin; mutating an option or registering a writer disables caching for that adapter. File cache entries are checked against dependency content hashes, including targets named by absolute paths inside the include root.
