import { defineConfig } from 'vitepress'
import { readFileSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

// Load custom Carve grammar for syntax highlighting
const carveGrammar = JSON.parse(
  readFileSync(resolve(__dirname, 'grammars/carve.tmLanguage.json'), 'utf-8')
)

export default defineConfig({
  title: 'Laravel Carve',
  description: 'Carve markup language integration for Laravel — Blade directives, services, validation, and caching',

  base: '/laravel-carve/',

  cleanUrls: true,

  head: [
    ['link', { rel: 'icon', href: '/laravel-carve/favicon.svg', type: 'image/svg+xml' }],
  ],

  markdown: {
    languages: [
      {
        ...carveGrammar,
        name: 'carve',
        aliases: ['crv', 'Carve'],
      },
    ],
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
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/' },
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
          ],
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Extensions', link: '/guide/extensions' },
            { text: 'Safe Mode', link: '/guide/safe-mode' },
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
      pattern: 'https://github.com/markup-carve/laravel-carve/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright Markup Carve',
    },
  },
})
