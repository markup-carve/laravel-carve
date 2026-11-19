# Laravel Carve 0.2

Laravel integration for the unreleased Carve 0.2 PHP engine. This branch targets Carve on the engine's next2 branch. It has no registry release.

## Install from Git

Composer is pinned to one carve-php commit through the VCS repository in composer.json. In an application, require this package from its next2 branch and keep the same engine pin. PHP 8.2, the DOM extension, and Laravel 12 or 13 are required.

## Use

The facade and Blade integration render Carve 0.2 source as HTML. Raw HTML is disabled by default. Named profiles, file-backed includes, validation, formatting, lint, HTML import, casts, and caching remain available.

The text, Markdown, and ANSI output methods are still present. They use the Carve writer registry and report that the writer is unavailable until it is registered. Markdown input import is also pending an engine API.

See the [0.2 migration guide](docs/guide/migration.md) for every changed call and setting, and the [documentation index](docs/README.md).
