<?php

namespace GhostCompiler\LaravelModelCaching\Builders;

use Closure;
use GhostCompiler\LaravelModelCaching\ModelCache;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;

class CachedEloquentBuilder extends Builder
{
    protected int|null $cacheTtl = null;

    protected bool $cacheForever = false;

    protected bool $skipCache = false;

    public function remember(int|null $ttl = null): static
    {
        $this->cacheTtl = $ttl ?? (int) config('model-cache.default_ttl', 3600);
        $this->cacheForever = false;
        $this->skipCache = false;

        return $this;
    }

    public function rememberForever(): static
    {
        $this->cacheTtl = null;
        $this->cacheForever = true;
        $this->skipCache = false;

        return $this;
    }

    public function dontCache(): static
    {
        $this->skipCache = true;
        $this->cacheForever = false;
        $this->cacheTtl = null;

        return $this;
    }

    public function get($columns = ['*'])
    {
        if (! $this->shouldCache()) {
            return parent::get($columns);
        }

        return $this->cacheQuery('get', [
            'columns' => $columns,
        ], fn () => parent::get($columns));
    }

    public function first($columns = ['*'])
    {
        if (! $this->shouldCache()) {
            return parent::first($columns);
        }

        return $this->cacheQuery('first', [
            'columns' => $columns,
        ], fn () => $this->withoutCaching(
            fn () => parent::first($columns)
        ));
    }

    public function firstOrFail($columns = ['*'])
    {
        if (! $this->shouldCache()) {
            return parent::firstOrFail($columns);
        }

        return $this->cacheQuery('firstOrFail', [
            'columns' => $columns,
        ], fn () => $this->withoutCaching(
            fn () => parent::firstOrFail($columns)
        ));
    }

    /**
     * @param  mixed  $id
     * @param  array<int, string>|string  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array) ? \Illuminate\Database\Eloquent\Collection<int, TModel> : TModel|null)
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return parent::find($id, $columns);
        }

        if (! $this->shouldCache()) {
            return parent::find($id, $columns);
        }

        return $this->cacheQuery('find', [
            'id' => $id,
            'columns' => $columns,
        ], fn () => $this->withoutCaching(
            fn () => parent::find($id, $columns)
        ));
    }

    /**
     * @param  mixed  $id
     * @param  array<int, string>|string  $columns
     * @return ($id is (\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|array) ? \Illuminate\Database\Eloquent\Collection<int, TModel> : TModel)
     */
    public function findOrFail($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return parent::findOrFail($id, $columns);
        }

        if (! $this->shouldCache()) {
            return parent::findOrFail($id, $columns);
        }

        return $this->cacheQuery('findOrFail', [
            'id' => $id,
            'columns' => $columns,
        ], fn () => $this->withoutCaching(
            fn () => parent::findOrFail($id, $columns)
        ));
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        if (! $this->shouldCache()) {
            return parent::paginate($perPage, $columns, $pageName, $page, $total);
        }

        return $this->cacheQuery('paginate', [
            'perPage' => $perPage,
            'columns' => $columns,
            'pageName' => $pageName,
            'page' => $page,
            'total' => $total,
        ], fn () => $this->withoutCaching(
            fn () => parent::paginate($perPage, $columns, $pageName, $page, $total)
        ));
    }

    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        if (! $this->shouldCache()) {
            return parent::simplePaginate($perPage, $columns, $pageName, $page);
        }

        return $this->cacheQuery('simplePaginate', [
            'perPage' => $perPage,
            'columns' => $columns,
            'pageName' => $pageName,
            'page' => $page,
        ], fn () => $this->withoutCaching(
            fn () => parent::simplePaginate($perPage, $columns, $pageName, $page)
        ));
    }

    public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        if (! $this->shouldCache()) {
            return parent::cursorPaginate($perPage, $columns, $cursorName, $cursor);
        }

        return $this->cacheQuery('cursorPaginate', [
            'perPage' => $perPage,
            'columns' => $columns,
            'cursorName' => $cursorName,
            'cursor' => $cursor,
        ], fn () => $this->withoutCaching(
            fn () => parent::cursorPaginate($perPage, $columns, $cursorName, $cursor)
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function cacheQuery(string $operation, array $payload, Closure $callback): mixed
    {
        return app(ModelCache::class)->remember(
            $this,
            $operation,
            $payload,
            $this->resolvedTtl(),
            $this->cacheForever,
            $callback,
        );
    }

    protected function shouldCache(): bool
    {
        if (! config('model-cache.enabled', true) || $this->skipCache) {
            return false;
        }

        return $this->cacheForever || $this->resolvedTtl() !== null;
    }

    protected function resolvedTtl(): ?int
    {
        if ($this->cacheForever) {
            return null;
        }

        if ($this->cacheTtl !== null) {
            return $this->cacheTtl;
        }

        if (config('model-cache.auto_remember', false)) {
            return (int) config('model-cache.default_ttl', 3600);
        }

        return null;
    }

    protected function withoutCaching(Closure $callback): mixed
    {
        $previous = $this->skipCache;
        $this->skipCache = true;

        try {
            return $callback();
        } finally {
            $this->skipCache = $previous;
        }
    }
}
