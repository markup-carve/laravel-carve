import { defineConfig } from 'vitepress'

const docsBase = process.env.DOCS_BASE ?? '/laravel-carve/'
const docsVersionText = process.env.DOCS_VERSION_TEXT
const docsLatestBase = process.env.DOCS_LATEST_BASE ?? '/laravel-carve/'
const docsOtherBase = process.env.DOCS_OTHER_BASE ?? '/laravel-carve/0.2/'
const siteUrl = (base: string) => `https://markup-carve.github.io${base}`

export default defineConfig({
  title: 'Laravel Carve 0.2',
  description: 'Carve 0.2 integration for Laravel',

  base: docsBase,

  cleanUrls: true,

  head: [
    ['link', { rel: 'icon', href: `${docsBase}favicon.svg`, type: 'image/svg+xml' }],
    // The unreleased series must not compete with the released docs in search results.
    // Marks a versioned build, so the theme styles only the version menu as a switch.
    ...(docsVersionText ? [['meta', { name: 'docs-version', content: docsVersionText }] as [string, Record<string, string>]] : []),
    ...(process.env.VITE_DOCS_SERIES === 'dev' ? [['meta', { name: 'robots', content: 'noindex' }] as [string, Record<string, string>]] : []),
  ],

  markdown: {
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      {
        text: 'Links',
        items: [
          { text: 'Playground', link: 'https://sandbox.dereuromark.de/sandbox/carve' },
          { text: 'Demo App', link: 'https://github.com/markup-carve/laravel-carve-demo' },
          { text: 'carve-php', link: 'https://github.com/markup-carve/carve-php' },
          { text: 'Symfony Bundle', link: 'https://github.com/markup-carve/symfony-carve' },
          { text: 'Carve Docs', link: 'https://markup-carve.github.io/carve/' },
          { text: 'Changelog', link: 'https://github.com/markup-carve/laravel-carve/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/markup-carve/laravel-carve' },
          { text: 'Issues', link: 'https://github.com/markup-carve/laravel-carve/issues' },
        ],
      },
      ...(docsVersionText ? [{
        text: docsVersionText,
        items: [
          { text: process.env.DOCS_LATEST_LABEL ?? '0.1 (latest)', link: siteUrl(docsLatestBase), target: '_self' },
          { text: process.env.DOCS_OTHER_LABEL ?? '0.2 (dev)', link: siteUrl(docsOtherBase), target: '_self' },
        ],
      }] : []),
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/' },
            { text: 'Migration to 0.2', link: '/guide/migration' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
          ],
        },
        {
          text: 'Usage',
          items: [
            { text: 'Blade Directives', link: '/guide/blade-usage' },
            { text: 'Service Usage', link: '/guide/service-usage' },
            { text: 'Validation', link: '/guide/validation' },
            { text: 'Authoring in Laravel', link: '/guide/authoring' },
            { text: 'Tiptap Columns', link: '/guide/tiptap' },
          ],
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Extensions', link: '/guide/extensions' },
            { text: 'Raw HTML and URL policy', link: '/guide/safe-mode' },
            { text: 'Caching', link: '/guide/caching' },
            { text: 'Carve Syntax', link: '/guide/carve-syntax' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/markup-carve/laravel-carve' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: `https://github.com/markup-carve/laravel-carve/edit/${process.env.DOCS_EDIT_BRANCH ?? 'main'}/docs/:path`,
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright Markup Carve',
    },
  },
})
