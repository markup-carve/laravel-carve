---
layout: home

hero:
  name: Laravel Carve 0.2
  text: Carve 0.2 for Laravel
  tagline: Blade directives, converter profiles, validation, Tiptap columns and caching for the unreleased Carve 0.2 engine
  image:
    src: /logo.svg
    alt: Laravel Carve
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: Upgrade from 0.1
      link: /guide/migration
    - theme: alt
      text: Extensions
      link: /guide/extensions
    - theme: alt
      text: GitHub
      link: https://github.com/markup-carve/laravel-carve

features:
  - icon: "⚔️"
    title: Blade Directives
    details: "@carve, @carveRaw, @carveText and the &lt;x-carve&gt; component render Carve in your Blade views"
    link: /guide/blade-usage
  - icon: "🔒"
    title: Safe by Default
    details: "Raw HTML stays off and unsafe URL schemes are denied. Enable raw_html per profile for trusted content only"
    link: /guide/safe-mode
  - icon: "🎭"
    title: Converter Profiles
    details: "Named converters for different contexts (default, comment, trusted), each with its own Carve profile, options and extensions"
    link: /guide/configuration
  - icon: "🧩"
    title: Extensible
    details: "Enable carve-php extensions per profile by name: autolink, table of contents, tabs, math, citations, wikilinks and more"
    link: /guide/extensions
  - icon: "✅"
    title: Validation
    details: "The ValidCarve rule checks input against a Carve profile, can run lint and caps the field length"
    link: /guide/validation
  - icon: "⚡"
    title: Caching
    details: "Optional output caching through any Laravel cache store, keyed by profile options and engine pin"
    link: /guide/caching
  - icon: "📝"
    title: Tiptap Columns
    details: "The AsCarveDocument cast reads Tiptap JSON as Carve to render, index, export and lint it, without a Node runtime"
    link: /guide/tiptap
  - icon: "📄"
    title: Carve Views
    details: "Render .crv view files through a named converter, without Blade evaluation"
    link: /guide/blade-usage
  - icon: "🛠️"
    title: Artisan Commands
    details: "carve:render, carve:lint and carve:fmt work on Carve files; carve:convert imports Markdown and HTML"
    link: /guide/authoring
---
