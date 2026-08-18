# Changelog

## Unreleased

## 0.1.5 - 2026-08-18

### Security

- Require carve-php `^0.1.5`, which probes **every** candidate in a list-valued
  URL attribute instead of trusting the value's leading scheme.
  `srcset="safe.png 1x, javascript:alert(1) 2x"` passed the probe on its second
  entry. Upgrade if you render untrusted Carve or import untrusted HTML.

### Changed

- Carve-php 0.1.5 changes rendered output: a list-table header cell now carries
  `scope`, and the Markdown target escapes `<` only where it would open markup
  and leaves a bare ampersand alone. Assertions on that markup may need updating.

### Added

- Add per-converter symbol replacements (`symbols`) and source-line attributes (`source_lines`)

### Fixed

- Fix the render cache serving one converter's HTML to another: the cache key now identifies the converter's settings, not just the source

## 0.1.4 - 2026-08-10

### Changed

- Require carve-php `^0.1.4`, the current security and parser/writer
  convergence release
- Replace the floating code-sniffer development dependency with the tagged
  `^0.6.0` series
- Move CI, documentation, and drift workflows to the current GitHub Actions
  runtime

## 0.1.3 - 2026-07-27

### Changed

- Require carve-php `^0.1.3` (cross-engine convergence: strict column-0,
  unresolved footnote-ref, tight-item trailing text, list looseness)

### Added

- `plantuml` extension shorthand on `ExtensionFactory`
- `TYPE_*` constants and a `types()` method on `ExtensionFactory`
- `img_fence` extension shorthand for the carve-php `ImgFenceExtension`
  (sanitized SVG `img`/`image` fence)

## 0.1.2 - 2026-07-10

- `toMarkdown()` and `toAnsi()` on the converter, manager and facade
- Documented the `mode` config key (static graceful degradation) and the
  full extension list; corrected the mentions extension option names

## 0.1.1 - 2026-07-09

- Fix code-sniffer dev dependency name; CI matrix on Laravel 12 and 13

## 0.1.0 - 2026-07-09

Initial release.

- Blade directives: `@carve`, `@carveRaw`, `@carveText`
- `Carve` facade and `CarveManager` with named converter profiles
- Config-driven profiles: safe mode, render mode (`interactive`/`static`
  graceful degradation), soft-break mode, XHTML output, extensions
- Extension factory covering the carve-php extension set (admonitions,
  citations, code callouts, code groups, details, fenced render/mermaid,
  glossary, heading numbers, index, list tables, math, mentions, spoiler,
  table of contents, tabs, wikilinks, and more)
- `ValidCarve` validation rule
- Content-hash-keyed render caching via any Laravel cache store
