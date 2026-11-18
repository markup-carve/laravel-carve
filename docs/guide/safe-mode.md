# Raw HTML and URL policy

Carve2 disables raw HTML by default. Set raw_html only for trusted content. The trusted raw HTML helper enables rawHtml for one call while retaining the default URL policy. Unsafe URL schemes stay denied. Set url_policy explicitly if the application needs a stricter scheme allowlist.

The old safe_mode key is replaced by raw_html plus url_policy. See the [migration guide](migration-02.md#rendering-and-profiles).
