---
name: carve-development
description: Use Laravel Carve 0.2 in Blade, services, validation, authoring, and file rendering.
---

# Laravel Carve 0.2

This branch uses the unreleased Carve engine pinned to a carve-php main commit. Read docs/guide/migration.md before changing source, profiles, or integrations.

Carve 0.2 is not Markdown. Strong uses a single asterisk and emphasis uses a slash. Bare underline, strike, and highlight delimiters are literal; use braced forms. Lists and headings after text generally need blank lines. Tables use native header cells and braced alignment. Run the 0.2 upgrade tool on old source and review each SILENT result.

The facade and Blade integration render HTML with raw HTML disabled by default. The trusted raw HTML helper enables rawHtml only; unsafe URL schemes remain denied. Config uses profile, raw_html, url_policy, mode, extensions, and writers. Never pass 0.1 safe_mode or extension classes into a 0.2 profile.

Use ValidCarve::preset('comment') for short untrusted fields and add lint or maxLength when needed. Carve::lint returns Carve Diagnostic objects with code, message, byte offsets, and file. Carve::parse returns a Carve Node.

The package supports HTML import. Markdown import remains a public entry point but reports unavailable until a Carve importer exists. The text, Markdown, and ANSI outputs use the writer registry and report unavailable until a writer is registered. Extensions use Carve definitions or providers; tabs has a shorthand, and document transforms wait for the D4 engine API.
