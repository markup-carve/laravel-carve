# Laravel Carve 0.2

Laravel integration for the unreleased Carve 0.2 PHP engine. This branch targets Carve 0.2 on the engine's main branch. It has no registry release.

## Install from Git

Composer is pinned to one carve-php commit through the VCS repository in composer.json. In an application, require this package from its main branch and keep the same engine pin. PHP 8.2, the DOM extension, and Laravel 12 or 13 are required.

## Use

The facade and Blade integration render Carve 0.2 source as HTML. Raw HTML is disabled by default. Named profiles, file-backed includes, validation, formatting, lint, HTML import, casts, and caching remain available.

The text, Markdown, and ANSI output methods use the writers the engine registers. `Carve::fromMarkdown()` imports GitHub Flavored Markdown, as in 0.1: tables, task lists, strikethrough and GitHub alerts (as admonitions) convert. `carve:convert` reads a `.md` file the same way. Pass `'markdown'` as the second argument (or `--from=markdown`) for strict CommonMark, which keeps a GFM table as escaped text.

See the [0.2 migration guide](docs/guide/migration.md) for every changed call and setting, and the [documentation index](docs/README.md).
