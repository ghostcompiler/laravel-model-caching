<p align="center">
  <img src="https://res.cloudinary.com/djgvfl1tv/image/upload/v1780666791/logo_mqnqn4.png" alt="Laravel Uploads Logo" width="180">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-10%20to%2013-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Built%20With-Laravel%20Model%20Caching-0F172A?style=for-the-badge" alt="Laravel Model Caching">
</p>

# Laravel Model Caching

Relationship-aware Eloquent model caching for Laravel.

This package is designed for applications where cached parent queries must be invalidated when loaded child models change. It is not only a query cache. It stores deterministic query results and records a lightweight dependency index from model instances to cache keys.

## Features

- Opt-in model caching through `HasModelCaching`
- `remember()`, `rememberForever()`, `dontCache()`, and optional `auto_remember` for trait-enabled models
- Cached read operations: `get`, `first`, `firstOrFail`, `find`, `findOrFail`, and pagination methods
- Nested eager-load aware cache keys
- Pagination-safe keys for `paginate()`, `simplePaginate()`, and `cursorPaginate()`
- Morph map versioning in cache keys
- Dependency index for precise invalidation on child updates
- Structured cache payloads for models and collections (safe for morph relations and eager loads)
- Cache tags when the selected Laravel cache driver supports them
- Model observer invalidation for saved, deleted, restored, and force deleted events
- Artisan commands for warming, inspecting, and flushing tracked entries

## Installation

```bash
composer require ghostcompiler/laravel-model-caching
```

Publish the config:

```bash
php artisan vendor:publish --tag=model-cache-config
```

Redis is recommended for production because the dependency index can use native Redis sets.

## Quick Start

Add the trait to models that should be cacheable:

```php
use GhostCompiler\LaravelModelCaching\Concerns\HasModelCaching;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasModelCaching;
}
```

The trait alone does not cache queries. Either call `remember()` on the chain or enable `auto_remember` in config.

Use `remember()` on normal Eloquent queries:

```php
$users = User::with('posts.comments')
    ->where('active', true)
    ->remember(60)
    ->get();

$tenant = User::whereHas('brand.domains', fn ($q) => $q->where('domain', $host))
    ->with('brand')
    ->remember(300)
    ->firstOrFail();
```

Enable automatic caching for all read queries on trait-enabled models:

```php
// config/model-cache.php
'auto_remember' => true,
```

Then `User::where('active', true)->first()` is cached with `default_ttl`. Use `dontCache()` on chains that must always hit the database.

Use the convenience APIs:

```php
$user = User::findCached(1);

$user->loadCached('posts.comments');
```

Disable caching on a query chain:

```php
$users = User::remember(600)
    ->dontCache()
    ->where('active', true)
    ->get();
```

## Cache Key Safety

Each key includes:

- Model class, table, and connection
- SQL and bindings
- Selected columns
- Limit, offset, groups, and orders
- Eager loads, including nested relation names
- Closure constraint source fingerprints when available
- Pagination page, page name, cursor, and per-page values
- Morph map and configured morph map version
- Optional auth and tenant context

These queries generate different keys:

```php
User::with('posts')->remember(60)->get();
User::with('posts.comments')->remember(60)->get();
User::with(['posts', 'roles'])->remember(60)->get();
```

## Dependency Invalidation

On a cache miss, the package stores the query result and walks the returned models plus loaded relations. It records only model primary keys in a dependency index:

```text
model-cache:dependency:user:1 -> [cache-key-a]
model-cache:dependency:post:5 -> [cache-key-a, cache-key-b]
model-cache:dependency:comment:9 -> [cache-key-b]
```

When `Post #5` is saved, deleted, or force deleted, only keys listed under `model-cache:dependency:post:5` are forgotten. No full application cache flush is required.

When a new `Post` is **created** (or soft-deleted row is **restored**), every cached query rooted on that model class is forgotten too — for example paginated user lists — so new rows can appear on the next request without waiting for TTL expiry:

```text
model-cache:dependency:user:{class-hash}:_class -> [page-1-key, page-2-key, ...]
```

Updates to an existing row still invalidate only that row's dependencies plus any list page that included that instance.

Cache tags are used as an extra layer when supported. Per-entry tag metadata is stored separately so entries can include result-specific tags such as `post:5` while still being readable later from a deterministic query key.

## Morph Relations

Polymorphic relations are supported through the actual related models returned by Eloquent and through morph context in the cache key. If you change Laravel's morph map aliases, bump:

```php
'morph_map_version' => 2,
```

This prevents old entries from colliding with new alias meanings.

## Pagination

Pagination methods are cached at the top-level builder operation:

```php
$users = User::with('posts')->remember(300)->paginate(10);
```

The key includes the page name, current page, per-page value, and cursor state where applicable.

## Configuration

```php
return [
    'enabled' => true,
    'default_ttl' => 3600,
    'auto_remember' => false,
    'store' => null,
    'prefix' => 'model-cache',
    'cache_tags' => true,
    'dependency_ttl' => 604800,
    'use_redis_sets' => true,
    'auto_observe_models' => true,
    'include_auth_id' => false,
    'context_callbacks' => [],
    'morph_map_version' => 1,
    'debug' => false,
];
```

For multi-tenant apps, add a context callback:

```php
'context_callbacks' => [
    'tenant' => fn () => tenant('id'),
],
```

## Commands

Warm a query:

```bash
php artisan model-cache:warm "App\Models\User" --with=posts --with=posts.comments --ttl=600 --limit=1000
```

Inspect keys depending on a model instance:

```bash
php artisan model-cache:inspect "App\Models\Post" 5
```

Flush keys depending on a model instance:

```bash
php artisan model-cache:flush "App\Models\Post" 5
```

Flush every key known to the package dependency index:

```bash
php artisan model-cache:flush --known
```

## Production Notes

- Treat `auto_remember` carefully: incidental reads on cacheable models are cached until TTL or model invalidation. Use `dontCache()` in admin or debug paths.
- Prefer Redis for high traffic APIs.
- Keep cacheable queries deterministic.
- Include tenant/auth context when query results differ by user or tenant.
- Use eager loading intentionally. The package tracks loaded relations, not unloaded graph possibilities.
- Bump `morph_map_version` after morph map changes.
- Use `rememberForever()` only for data that is always invalidated by model events.

## Testing

```bash
composer install
composer test
```

## Development And Build Environment

This package was developed using **ServBay** as the local development environment.

### Development Tool Used

- Local development tool: `ServBay`
- Website: [www.servbay.com](https://www.servbay.com/)

### ServBay your development friend

<p>
  <img src="https://res.cloudinary.com/djgvfl1tv/image/upload/v1780667063/servbay_edc7jz.png" alt="ServBay Icon" width="96">
</p>

### Testing And Build Machine

- Tested on: `Mac M4`
- Built on: `Mac M4`