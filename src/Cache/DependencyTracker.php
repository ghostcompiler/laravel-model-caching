<?php

namespace GhostCompiler\LaravelModelCaching\Cache;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class DependencyTracker
{
    /**
     * @param  array<int, string>  $tags
     */
    public function track(string $cacheKey, mixed $value, array $tags, int|null $ttl = null, Model|null $queryModel = null): void
    {
        $dependencies = $this->extractDependencies($value);

        if ($queryModel !== null) {
            $dependencies[] = $this->classDependencyKey($queryModel);
        }

        $dependencies = array_values(array_unique($dependencies));
        $ttl ??= (int) config('model-cache.dependency_ttl', 604800);

        $this->putArray($this->metaKey($cacheKey), [
            'tags' => array_values(array_unique($tags)),
            'dependencies' => $dependencies,
        ], $ttl);

        $this->addToSet($this->allKeysKey(), $cacheKey, $ttl);

        foreach ($dependencies as $dependency) {
            $this->addToSet($dependency, $cacheKey, $ttl);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tagsForCacheKey(string $cacheKey): array
    {
        $meta = Cache::store(config('model-cache.store'))->get($this->metaKey($cacheKey), []);

        return is_array($meta) && isset($meta['tags']) && is_array($meta['tags'])
            ? $meta['tags']
            : [];
    }

    public function invalidateModel(Model $model): int
    {
        $dependency = $this->dependencyKey($model);
        $cacheKeys = $this->members($dependency);
        $count = 0;

        foreach ($cacheKeys as $cacheKey) {
            $this->forgetCacheKey($cacheKey);
            $count++;
        }

        $this->forgetSet($dependency);

        return $count;
    }

    public function invalidateClassQueries(Model|string $model): int
    {
        $dependency = $this->classDependencyKey($model);
        $cacheKeys = $this->members($dependency);
        $count = 0;

        foreach ($cacheKeys as $cacheKey) {
            $this->forgetCacheKey($cacheKey);
            $count++;
        }

        $this->forgetSet($dependency);

        return $count;
    }

    public function flushKnownCacheKeys(): int
    {
        $keys = $this->members($this->allKeysKey());
        $count = 0;

        foreach ($keys as $cacheKey) {
            $this->forgetCacheKey($cacheKey);
            $count++;
        }

        $this->forgetSet($this->allKeysKey());

        return $count;
    }

    /**
     * @return array<int, string>
     */
    public function cacheKeysForModel(Model $model): array
    {
        return $this->members($this->dependencyKey($model));
    }

    /**
     * @return array<int, string>
     */
    public function cacheKeysForClass(Model|string $model): array
    {
        return $this->members($this->classDependencyKey($model));
    }

    protected function forgetCacheKey(string $cacheKey): void
    {
        $repository = Cache::store(config('model-cache.store'));
        $meta = $repository->get($this->metaKey($cacheKey), []);
        $tags = is_array($meta) && is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
        $dependencies = is_array($meta) && is_array($meta['dependencies'] ?? null) ? $meta['dependencies'] : [];

        try {
            if ($tags !== []) {
                $repository->tags($tags)->forget($cacheKey);
            } else {
                $repository->forget($cacheKey);
            }
        } catch (Throwable) {
            $repository->forget($cacheKey);
        }

        foreach ($dependencies as $dependency) {
            $this->removeFromSet((string) $dependency, $cacheKey);
        }

        $this->removeFromSet($this->allKeysKey(), $cacheKey);
        $repository->forget($this->metaKey($cacheKey));
    }

    /**
     * @return array<int, string>
     */
    protected function extractDependencies(mixed $value): array
    {
        $dependencies = [];
        $seen = [];

        $this->walk($value, $dependencies, $seen);

        return array_values(array_unique($dependencies));
    }

    /**
     * @param  array<int, string>  $dependencies
     * @param  array<string, bool>  $seen
     */
    protected function walk(mixed $value, array &$dependencies, array &$seen): void
    {
        if ($value instanceof Model) {
            $objectId = spl_object_hash($value);

            if (isset($seen[$objectId])) {
                return;
            }

            $seen[$objectId] = true;

            if ($value->exists && $value->getKey() !== null) {
                $dependencies[] = $this->dependencyKey($value);
            }

            foreach ($value->getRelations() as $relationValue) {
                $this->walk($relationValue, $dependencies, $seen);
            }

            return;
        }

        if ($value instanceof AbstractPaginator) {
            $this->walk($value->getCollection(), $dependencies, $seen);

            return;
        }

        if ($value instanceof EloquentCollection || $value instanceof Collection || is_array($value)) {
            foreach ($value as $item) {
                $this->walk($item, $dependencies, $seen);
            }
        }
    }

    protected function dependencyKey(Model $model): string
    {
        return $this->dependencyKeyForClass($model::class).':'.$model->getKey();
    }

    protected function classDependencyKey(Model|string $model): string
    {
        $class = $model instanceof Model ? $model::class : $model;

        return $this->dependencyKeyForClass($class).':_class';
    }

    protected function dependencyKeyForClass(string $class): string
    {
        $prefix = trim((string) config('model-cache.prefix', 'model-cache'), ':');
        $alias = Str::of(class_basename($class))->snake()->replace('\\', '_')->toString();
        $classHash = substr(hash('xxh128', $class), 0, 12);

        return "{$prefix}:dependency:{$alias}:{$classHash}";
    }

    protected function metaKey(string $cacheKey): string
    {
        return trim((string) config('model-cache.prefix', 'model-cache'), ':').':meta:'.hash('sha256', $cacheKey);
    }

    protected function allKeysKey(): string
    {
        return trim((string) config('model-cache.prefix', 'model-cache'), ':').':keys';
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function putArray(string $key, array $value, int $ttl): void
    {
        Cache::store(config('model-cache.store'))->put($key, $value, $ttl);
    }

    protected function addToSet(string $key, string $member, int $ttl): void
    {
        if ($this->redisCommand('sadd', [$key, $member]) !== false) {
            $this->redisCommand('expire', [$key, $ttl]);
            Cache::store(config('model-cache.store'))->put($key.':ttl', true, $ttl);

            return;
        }

        $members = Cache::store(config('model-cache.store'))->get($key, []);
        $members = is_array($members) ? $members : [];
        $members[] = $member;

        Cache::store(config('model-cache.store'))->put($key, array_values(array_unique($members)), $ttl);
    }

    /**
     * @return array<int, string>
     */
    protected function members(string $key): array
    {
        $redisMembers = $this->redisCommand('smembers', [$key]);

        if (is_array($redisMembers)) {
            return array_map('strval', $redisMembers);
        }

        $members = Cache::store(config('model-cache.store'))->get($key, []);

        return is_array($members) ? array_map('strval', $members) : [];
    }

    protected function removeFromSet(string $key, string $member): void
    {
        if ($this->redisCommand('srem', [$key, $member]) !== false) {
            return;
        }

        $members = Cache::store(config('model-cache.store'))->get($key, []);

        if (! is_array($members)) {
            return;
        }

        Cache::store(config('model-cache.store'))->put($key, array_values(array_diff($members, [$member])), (int) config('model-cache.dependency_ttl', 604800));
    }

    protected function forgetSet(string $key): void
    {
        if ($this->redisCommand('del', [$key]) !== false) {
            return;
        }

        Cache::store(config('model-cache.store'))->forget($key);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    protected function redisCommand(string $command, array $arguments): mixed
    {
        if (! config('model-cache.use_redis_sets', true)) {
            return false;
        }

        try {
            $store = Cache::store(config('model-cache.store'))->getStore();

            if (! method_exists($store, 'connection')) {
                return false;
            }

            return $store->connection()->command($command, $arguments);
        } catch (Throwable) {
            return false;
        }
    }
}
