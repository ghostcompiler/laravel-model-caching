<?php

namespace GhostCompiler\LaravelModelCaching\Observers;

use GhostCompiler\LaravelModelCaching\ModelCache;
use Illuminate\Database\Eloquent\Model;

class ModelCacheObserver
{
    public function __construct(protected ModelCache $cache)
    {
    }

    public function saved(Model $model): void
    {
        $this->cache->invalidateModel($model);
    }

    public function created(Model $model): void
    {
        $this->cache->invalidateClassQueries($model);
    }

    public function restored(Model $model): void
    {
        $this->cache->invalidateClassQueries($model);
        $this->cache->invalidateModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->cache->invalidateModel($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->cache->invalidateModel($model);
    }
}
