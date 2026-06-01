<?php

namespace GhostCompiler\LaravelModelCaching\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class TagManager
{
    public function supportsTags(): bool
    {
        if (! config('model-cache.cache_tags', true)) {
            return false;
        }

        try {
            Cache::store(config('model-cache.store'))->tags(['model-cache:probe']);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public function tagsFor(Builder $builder, mixed $value): array
    {
        $tags = [
            $this->globalTag(),
            'model:'.$this->modelAlias($builder->getModel()),
        ];

        foreach (array_keys($builder->getEagerLoads()) as $relation) {
            $tags[] = 'relation:'.$this->relationAlias((string) $relation);
        }

        foreach ($this->modelsFrom($value) as $model) {
            array_push($tags, ...$this->tagsForModel($model));
        }

        return array_values(array_unique(array_filter($tags)));
    }

    /**
     * @return array<int, string>
     */
    public function tagsForModel(Model $model): array
    {
        $alias = $this->modelAlias($model);
        $key = $model->getKey();

        return array_values(array_filter([
            $this->globalTag(),
            'model:'.$alias,
            $key !== null ? $alias.':'.$key : null,
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function instanceTagsForModel(Model $model): array
    {
        $alias = $this->modelAlias($model);
        $key = $model->getKey();

        return $key !== null ? [$alias.':'.$key] : [];
    }

    public function globalTag(): string
    {
        return trim((string) config('model-cache.prefix', 'model-cache'), ':');
    }

    public function modelScopeTag(Model|string $model): string
    {
        $class = $model instanceof Model ? $model::class : $model;

        return 'model:'.$this->modelAliasForClass($class);
    }

    protected function modelAlias(Model $model): string
    {
        return $this->modelAliasForClass($model::class);
    }

    protected function modelAliasForClass(string $class): string
    {
        return Str::of(class_basename($class))->snake()->replace('\\', '_')->toString();
    }

    protected function relationAlias(string $relation): string
    {
        return str_replace(['\\', ' '], ['.', '_'], $relation);
    }

    /**
     * @return array<int, Model>
     */
    protected function modelsFrom(mixed $value): array
    {
        $seen = [];

        return $this->walkModels($value, $seen);
    }

    /**
     * @param  array<string, bool>  $seen
     * @return array<int, Model>
     */
    protected function walkModels(mixed $value, array &$seen): array
    {
        $models = [];

        if ($value instanceof Model) {
            $objectId = spl_object_hash($value);

            if (isset($seen[$objectId])) {
                return [];
            }

            $seen[$objectId] = true;
            $models[] = $value;

            foreach ($value->getRelations() as $relationValue) {
                array_push($models, ...$this->walkModels($relationValue, $seen));
            }
        } elseif ($value instanceof AbstractPaginator) {
            array_push($models, ...$this->walkModels($value->getCollection(), $seen));
        } elseif ($value instanceof EloquentCollection || $value instanceof Collection) {
            foreach ($value as $item) {
                array_push($models, ...$this->walkModels($item, $seen));
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                array_push($models, ...$this->walkModels($item, $seen));
            }
        }

        return $models;
    }
}
