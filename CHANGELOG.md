# Changelog

## 0.1.0 - unreleased

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
