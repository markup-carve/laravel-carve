# Extensions

The 0.2 extension host is provisional. Pass Carve ExtensionDefinition or ExtensionProvider objects in a converter profile's extensions list. The tabs shorthand registers the standard Carve tabs definitions. The plantuml shorthand registers fenced renderers for `plantuml` and `puml`; its optional `css_class` sets the single class on the escaped HTML code block. The old 0.1 extension classes cannot run on a Carve tree and cause a clear error if configured.

Document transforms and some renderer hooks are pending the engine API. See the [migration guide](migration.md#extensions) and the [Carve extension guide](https://github.com/markup-carve/carve-php/blob/next2/docs/README.md#extensions-provisional).
