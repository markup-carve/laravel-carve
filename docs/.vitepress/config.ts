import { defineConfig } from 'vitepress'
import { readFileSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'
import githubLight from '@shikijs/themes/github-light'
import githubDark from '@shikijs/themes/github-dark'

const __dirname = dirname(fileURLToPath(import.meta.url))

// Load custom Carve grammar for syntax highlighting
const carveGrammar = JSON.parse(
  readFileSync(resolve(__dirname, 'grammars/carve.tmLanguage.json'), 'utf-8')
)

// Extend the bundled GitHub themes with rules for Carve scopes that don't
// have stock styling (highlight, sub/superscript, tables, admonitions, ...).
// Mirrors the canonical carve docs config so fences render identically.
const carveLightExtras = [
  { scope: 'markup.bold.italic', settings: { foreground: '#24292e', fontStyle: 'italic bold' } },
  { scope: 'markup.highlight', settings: { foreground: '#b08800', fontStyle: 'bold' } },
  { scope: 'markup.superscript', settings: { foreground: '#6f42c1' } },
  { scope: 'markup.subscript', settings: { foreground: '#6f42c1' } },
  { scope: 'markup.raw.inline', settings: { foreground: '#005cc5' } },
  { scope: 'markup.raw.code', settings: { foreground: '#6a737d' } },
  { scope: 'fenced_code.block.language', settings: { foreground: '#22863a', fontStyle: 'bold' } },
  { scope: 'punctuation.definition.fenced', settings: { foreground: '#959da5' } },
  { scope: 'punctuation.definition.raw', settings: { foreground: '#959da5' } },
  { scope: ['punctuation.definition.list.unnumbered', 'punctuation.definition.list.numbered', 'punctuation.definition.list'], settings: { foreground: '#d73a49', fontStyle: 'bold' } },
  { scope: 'punctuation.definition.checkbox', settings: { foreground: '#959da5' } },
  { scope: 'constant.language.checkbox', settings: { foreground: '#22863a', fontStyle: 'bold' } },
  { scope: 'keyword.operator.table.header', settings: { foreground: '#d73a49', fontStyle: 'bold' } },
  { scope: ['keyword.operator.table.rowspan', 'keyword.operator.table.colspan', 'keyword.operator.table.continuation'], settings: { foreground: '#e36209', fontStyle: 'bold' } },
  { scope: 'punctuation.separator.table', settings: { foreground: '#959da5' } },
  { scope: 'punctuation.definition.admonition', settings: { foreground: '#d73a49', fontStyle: 'bold' } },
  { scope: 'entity.name.tag.admonition', settings: { foreground: '#22863a', fontStyle: 'bold' } },
  { scope: 'string.unquoted.admonition.title', settings: { foreground: '#032f62' } },
  { scope: 'punctuation.definition.caption', settings: { foreground: '#e36209', fontStyle: 'bold' } },
  { scope: 'markup.caption', settings: { foreground: '#6a737d', fontStyle: 'italic' } },
  { scope: 'meta.attributes', settings: { foreground: '#e36209' } },
  { scope: 'punctuation.definition.attributes', settings: { foreground: '#959da5' } },
  { scope: 'punctuation.definition.mention', settings: { foreground: '#d73a49' } },
  { scope: 'variable.other.mention', settings: { foreground: '#d73a49', fontStyle: 'bold' } },
  { scope: 'punctuation.definition.tag', settings: { foreground: '#22863a' } },
  { scope: 'variable.other.tag', settings: { foreground: '#22863a', fontStyle: 'bold' } },
  { scope: 'entity.name.abbreviation', settings: { foreground: '#005cc5', fontStyle: 'bold' } },
  { scope: 'string.unquoted.abbreviation', settings: { foreground: '#6a737d', fontStyle: 'italic' } },
]

const carveDarkExtras = [
  { scope: 'markup.bold.italic', settings: { foreground: '#e1e4e8', fontStyle: 'italic bold' } },
  { scope: 'markup.highlight', settings: { foreground: '#ffd33d', fontStyle: 'bold' } },
  { scope: 'markup.superscript', settings: { foreground: '#b392f0' } },
  { scope: 'markup.subscript', settings: { foreground: '#b392f0' } },
  { scope: 'markup.raw.inline', settings: { foreground: '#79b8ff' } },
  { scope: 'markup.raw.code', settings: { foreground: '#959da5' } },
  { scope: 'fenced_code.block.language', settings: { foreground: '#85e89d', fontStyle: 'bold' } },
  { scope: 'punctuation.definition.fenced', settings: { foreground: '#6a737d' } },
  { scope: 'punctuation.definition.raw', settings: { foreground: '#6a737d' } },
  { scope: ['punctuation.definition.list.unnumbered', 'punctuation.definition.list.numbered', 'punctuation.definition.list'], settings: { foreground: '#f97583', fontStyle: 'bold' } },
  { scope: 'punctuation.definition.checkbox', settings: { foreground: '#6a737d' } },
  { scope: 'constant.language.checkbox', settings: { foreground: '#85e89d', fontStyle: 'bold' } },
  { scope: 'keyword.operator.table.header', settings: { foreground: '#f97583', fontStyle: 'bold' } },
  { scope: ['keyword.operator.table.rowspan', 'keyword.operator.table.colspan', 'keyword.operator.table.continuation'], settings: { foreground: '#ffab70', fontStyle: 'bold' } },
  { scope: 'punctuation.separator.table', settings: { foreground: '#6a737d' } },
  { scope: 'punctuation.definition.admonition', settings: { foreground: '#f97583', fontStyle: 'bold' } },
  { scope: 'entity.name.tag.admonition', settings: { foreground: '#85e89d', fontStyle: 'bold' } },
  { scope: 'string.unquoted.admonition.title', settings: { foreground: '#79b8ff' } },
  { scope: 'punctuation.definition.caption', settings: { foreground: '#ffab70', fontStyle: 'bold' } },
  { scope: 'markup.caption', settings: { foreground: '#959da5', fontStyle: 'italic' } },
  { scope: 'meta.attributes', settings: { foreground: '#ffab70' } },
  { scope: 'punctuation.definition.attributes', settings: { foreground: '#6a737d' } },
  { scope: 'punctuation.definition.mention', settings: { foreground: '#f97583' } },
  { scope: 'variable.other.mention', settings: { foreground: '#f97583', fontStyle: 'bold' } },
  { scope: 'punctuation.definition.tag', settings: { foreground: '#85e89d' } },
  { scope: 'variable.other.tag', settings: { foreground: '#85e89d', fontStyle: 'bold' } },
  { scope: 'entity.name.abbreviation', settings: { foreground: '#79b8ff', fontStyle: 'bold' } },
  { scope: 'string.unquoted.abbreviation', settings: { foreground: '#959da5', fontStyle: 'italic' } },
]

const carveLightTheme = {
  ...githubLight,
  tokenColors: [...(githubLight.tokenColors ?? []), ...carveLightExtras],
}

const carveDarkTheme = {
  ...githubDark,
  tokenColors: [...(githubDark.tokenColors ?? []), ...carveDarkExtras],
}

// Shiki sets fontStyle bit 8 for strikethrough on the token but does not
// emit `text-decoration: line-through` in its dual-theme HTML output.
// Bridge that with a transformer that tags tokens so CSS can style them
// (see theme/custom.css).
const FontStyle = { Italic: 1, Bold: 2, Underline: 4, Strikethrough: 8 }

const carveStylingTransformer = {
  name: 'carve-extras',
  preprocess(_code: string, options: Record<string, unknown>) {
    options.includeExplanation = 'scopeName'
  },
  tokens(tokens: Array<Array<{
    fontStyle?: number
    htmlAttrs?: Record<string, string>
    explanation?: Array<{ scopes: Array<{ scopeName: string }> }>
  }>>) {
    for (const line of tokens) {
      for (const tk of line) {
        const scopes = tk.explanation?.flatMap((e) =>
          e.scopes.map((s) => s.scopeName),
        ) ?? []
        const hasScope = (prefix: string) => scopes.some((s) => s.startsWith(prefix))

        const mark = (attr: string) => {
          if (!tk.htmlAttrs) tk.htmlAttrs = {}
          tk.htmlAttrs[attr] = ''
        }

        if ((tk.fontStyle ?? 0) & FontStyle.Strikethrough || hasScope('markup.strikethrough')) {
          mark('data-carve-strike')
        }
        if (hasScope('markup.superscript')) mark('data-carve-super')
        if (hasScope('markup.subscript')) mark('data-carve-sub')
        if (hasScope('markup.highlight')) mark('data-carve-highlight')
      }
    }
  },
}

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
    theme: { light: carveLightTheme, dark: carveDarkTheme },
    codeTransformers: [carveStylingTransformer],
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
