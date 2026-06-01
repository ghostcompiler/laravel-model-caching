# Developer Guide

This guide shows how to use this package from a downloaded local copy before it is published to Packagist.

## Local Path Installation

Assume the package is downloaded here:

```bash
/Users/ghostcompiler/Desktop/laravel-model-caching
```

In your Laravel application's `composer.json`, add a local path repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-model-caching",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Use the correct relative path from your Laravel app to this package. For example, if both projects are on the Desktop:

```json
"url": "../laravel-model-caching"
```

Then require the package inside your Laravel app:

```bash
composer require ghostcompiler/laravel-model-caching:@dev
```

For Laravel 13 apps, make sure this package version includes Laravel 13 constraints:

```json
"illuminate/cache": "^10.0|^11.0|^12.0|^13.0"
```

If Composer already has a cached package resolution, update it:

```bash
composer update ghostcompiler/laravel-model-caching
```

If the package was previously rejected by Composer, run this from the Laravel app after updating the downloaded package:

```bash
composer clear-cache
composer require ghostcompiler/laravel-model-caching:@dev -W
```

The `-W` flag allows Composer to resolve related dependencies when needed.

## Publish Config

```bash
php artisan vendor:publish --tag=model-cache-config
```

This creates:

```bash
config/model-cache.php
```

## Enable on Models

Add the trait only to models that should support caching:

```php
use GhostCompiler\LaravelModelCaching\Concerns\HasModelCaching;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasModelCaching;
}
```

Then use:

```php
User::with('posts.comments')->remember()->get();
User::with('posts')->remember(300)->paginate(10);
User::where('active', true)->remember()->firstOrFail();
User::findCached(1);
```

`remember()` with no argument uses `default_ttl` from `config/model-cache.php`.

The trait does not cache by itself. Cached builder methods are `get`, `first`, `firstOrFail`, `find`, `findOrFail`, and pagination helpers.

To cache every read on trait-enabled models without chaining `remember()`:

```php
'auto_remember' => true,
```

Use `dontCache()` to skip caching for a specific chain.

## Recommended Local Config

For development:

```php
'store' => null,
'auto_remember' => false,
'cache_tags' => false,
'debug' => true,
```

For tenant middleware that resolves on every request:

```php
'auto_remember' => true,
'debug' => true,
'context_callbacks' => [
    'host' => fn () => request()->getHost(),
],
```

For production with Redis:

```php
'store' => 'redis',
'cache_tags' => true,
'use_redis_sets' => true,
```

For multi-tenant apps, include tenant context:

```php
'context_callbacks' => [
    'tenant' => fn () => tenant('id'),
],
```

## Package Development Commands

From this package directory:

```bash
composer install
composer validate --strict
composer analyse
composer test
```

Run one syntax check manually:

```bash
find src config tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Testing in a Laravel App

After path installing, use the package exactly like a normal dependency:

```bash
php artisan config:clear
php artisan cache:clear
php artisan model-cache:warm "App\Models\User" --with=posts --ttl=600
php artisan model-cache:inspect "App\Models\Post" 5
php artisan model-cache:flush "App\Models\Post" 5
```

If Laravel does not auto-discover the provider, add it manually in `config/app.php`:

```php
'providers' => [
    GhostCompiler\LaravelModelCaching\ModelCacheServiceProvider::class,
],
```

## GitHub Actions

This repository includes a test workflow at:

```bash
.github/workflows/tests.yml
```

It runs:

- Composer validation
- PHP syntax checks
- PHPUnit on Laravel 10, 11, 12, and 13

The workflow runs on pushes and pull requests to `main` and `master`.

## Releasing Later

When the package is ready to publish:

1. Push this repository to GitHub.
2. Create a tagged release, for example `v1.0.0`.
3. Submit the repository to Packagist.
4. In Laravel apps, replace the path repository install with:

```bash
composer require ghostcompiler/laravel-model-caching
```
