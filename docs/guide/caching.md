# Caching

Set cache.enabled to true to use a Laravel cache store. cache.store, cache.ttl, and cache.prefix select the store and entry policy. A profile's options and engine pin separate cached outputs. File rendering checks the content hashes of included dependencies before reusing an entry. Changing an option or registering a writer disables caching on that adapter instance.

See the [migration guide](migration-02.md#cache-and-result-data).
