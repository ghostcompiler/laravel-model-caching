# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-06-02

### Added

- `HasModelCaching` trait with `remember()`, `rememberForever()`, `dontCache()`, `findCached()`, and `loadCached()`
- Cached read operations: `get`, `first`, `firstOrFail`, `find`, `findOrFail`, `paginate`, `simplePaginate`, and `cursorPaginate`
- Relationship-aware dependency index for precise cache invalidation
- Class-level invalidation on `created` and `restored` so paginated lists include new rows on the next request
- Structured cache serialization safe for morph relations, eager loads, and JSON cache stores
- Optional `auto_remember`, cache tags, context callbacks, and morph map versioning
- Artisan commands: `model-cache:warm`, `model-cache:inspect`, and `model-cache:flush`
