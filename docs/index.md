---
layout: home

hero:
  name: Laravel Carve
  text: Carve for Laravel
  tagline: Blade directives, services, validation, and caching for the Carve markup language
  image:
    src: /logo.svg
    alt: Laravel Carve
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: Extensions
      link: /guide/extensions
    - theme: alt
      text: View on GitHub
      link: https://github.com/markup-carve/laravel-carve

features:
  - icon: "\u2694\uFE0F"
    title: Blade Directives
    details: "@carve, @carveRaw, @carveText \u2014 render Carve markup directly in your Blade views"
  - icon: "\uD83D\uDD12"
    title: Safe Mode
    details: Built-in XSS protection enabled by default for untrusted user input
  - icon: "\uD83C\uDFAD"
    title: Multiple Profiles
    details: Different converter configurations for different contexts (user content, admin, CMS)
  - icon: "\uD83E\uDDE9"
    title: Extensible
    details: Enable carve-php's bundled extensions per profile \u2014 autolink, mentions, TOC, tabs, math, citations, and more
  - icon: "\u2705"
    title: Validation
    details: ValidCarve rule for request validation of Carve markup input
  - icon: "\u26A1"
    title: Caching
    details: Optional output caching via any Laravel cache store for better performance
---
