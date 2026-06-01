<?php

namespace GhostCompiler\LaravelModelCaching;

use Closure;
use GhostCompiler\LaravelModelCaching\Cache\CacheValueSerializer;
use GhostCompiler\LaravelModelCaching\Cache\DependencyTracker;
use GhostCompiler\LaravelModelCaching\Cache\TagManager;
use GhostCompiler\LaravelModelCaching\Contracts\CacheKeyGenerator;
use Illuminate\Contracts\Cache\Repository as CacheRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModelCache
{
    public function __construct(
        protected CacheKeyGenerator $keys,
        protected DependencyTracker $dependencies,
        protected TagManager $tags,
        protected CacheValueSerializer $serializer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function remember(
        Builder $builder,
        string $operation,
        array $payload,
        int|null $ttl,
        bool $forever,
        Closure $callback
    ): mixed {
        $key = $this->keys->generate($builder, $operation, $payload);
        $storedTags = $this->dependencies->tagsForCacheKey($key);
        $repository = $this->repository($storedTags);

        if ($repository->has($key)) {
            $stored = $repository->get($key);
            $value = $this->serializer->restoreFromStorage($stored);

            if ($value !== null) {
                $this->trace('hit', $key, $storedTags);

                return $value;
            }

            $repository->forget($key);
            $this->trace('corrupt', $key, $storedTags);
        }

        $this->trace('miss', $key, $storedTags);

        $value = $callback();
        $tags = $this->tags->tagsFor($builder, $value);
        $repository = $this->repository($tags);
        $storedValue = $this->serializer->prepareForStorage($value);

        if ($forever) {
            $repository->forever($key, $storedValue);
        } else {
            $repository->put($key, $storedValue, $ttl ?? (int) config('model-cache.default_ttl', 3600));
        }

        $this->dependencies->track($key, $value, $tags, $ttl, $builder->getModel());

        return $value;
    }

    public function invalidateModel(Model $model): void
    {
        $this->dependencies->invalidateModel($model);

        $modelTags = $this->tags->instanceTagsForModel($model);

        if ($this->tags->supportsTags() && $modelTags !== []) {
            $this->repository($modelTags)->flush();
        }
    }

    public function invalidateClassQueries(Model $model): void
    {
        $this->dependencies->invalidateClassQueries($model);

        if ($this->tags->supportsTags()) {
            $this->repository([
                $this->tags->globalTag(),
                $this->tags->modelScopeTag($model),
            ])->flush();
        }
    }

    public function flushKnown(): int
    {
        return $this->dependencies->flushKnownCacheKeys();
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function repository(array $tags = []): CacheRepositoryContract
    {
        $repository = Cache::store(config('model-cache.store'));

        if ($tags !== [] && $this->tags->supportsTags()) {
            return $repository->tags($tags);
        }

        return $repository;
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function trace(string $event, string $key, array $tags): void
    {
        if (! config('model-cache.debug', false)) {
            return;
        }

        Log::debug("Model cache {$event}", [
            'key' => $key,
            'tags' => $tags,
        ]);
    }
}
