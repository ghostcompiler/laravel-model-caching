<?php

namespace GhostCompiler\LaravelModelCaching\Concerns;

use GhostCompiler\LaravelModelCaching\Builders\CachedEloquentBuilder;
use GhostCompiler\LaravelModelCaching\ModelCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasModelCaching
{
    public static function bootHasModelCaching(): void
    {
        if (config('model-cache.auto_observe_models', true)) {
            foreach (['saved', 'deleted', 'forceDeleted'] as $event) {
                static::registerModelEvent(
                    $event,
                    fn (Model $model) => app(ModelCache::class)->invalidateModel($model),
                );
            }

            static::registerModelEvent(
                'created',
                fn (Model $model) => app(ModelCache::class)->invalidateClassQueries($model),
            );

            static::registerModelEvent(
                'restored',
                function (Model $model) {
                    $cache = app(ModelCache::class);
                    $cache->invalidateClassQueries($model);
                    $cache->invalidateModel($model);
                },
            );
        }
    }

    public function newEloquentBuilder($query): Builder
    {
        return new CachedEloquentBuilder($query);
    }

    /**
     * @param  array<int, string>|string  $relations
     */
    public function loadCached(array|string $relations, int|null $ttl = null): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        if (isset($relations[1]) && is_int($relations[1])) {
            $ttl = $relations[1];
            $relations = [$relations[0]];
        }

        $fresh = static::query()
            ->whereKey($this->getKey())
            ->with($relations)
            ->remember($ttl ?? (int) config('model-cache.default_ttl', 3600))
            ->first();

        if ($fresh instanceof Model) {
            foreach ($this->topLevelRelations($relations) as $relation) {
                if ($fresh->relationLoaded($relation)) {
                    $this->setRelation($relation, $fresh->getRelation($relation));
                }
            }
        }

        return $this;
    }

    /**
     * @param  array<int, string>|string  $relations
     * @return array<int, string>
     */
    protected function topLevelRelations(array|string $relations): array
    {
        $relations = is_string($relations) ? [$relations] : $relations;
        $isList = array_keys($relations) === range(0, max(0, count($relations) - 1));

        return array_values(array_unique(array_map(
            fn (string|int $relation) => explode('.', (string) $relation)[0],
            $isList ? $relations : array_keys($relations),
        )));
    }

    /**
     * @param  array<int, string>|string  $columns
     */
    public static function findCached(mixed $id, array|string $columns = ['*'], int|null $ttl = null): ?static
    {
        return static::query()
            ->remember($ttl ?? (int) config('model-cache.default_ttl', 3600))
            ->find($id, $columns);
    }

    public function flushModelCache(): void
    {
        app(ModelCache::class)->invalidateModel($this);
    }
}
