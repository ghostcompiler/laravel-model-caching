<?php

namespace GhostCompiler\LaravelModelCaching;

use GhostCompiler\LaravelModelCaching\Cache\CacheKeyGenerator;
use GhostCompiler\LaravelModelCaching\Cache\CacheValueSerializer;
use GhostCompiler\LaravelModelCaching\Cache\DependencyTracker;
use GhostCompiler\LaravelModelCaching\Cache\TagManager;
use GhostCompiler\LaravelModelCaching\Commands\FlushModelCacheCommand;
use GhostCompiler\LaravelModelCaching\Commands\InspectModelCacheCommand;
use GhostCompiler\LaravelModelCaching\Commands\WarmModelCacheCommand;
use GhostCompiler\LaravelModelCaching\Contracts\CacheKeyGenerator as CacheKeyGeneratorContract;
use Illuminate\Support\ServiceProvider;

class ModelCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/model-cache.php', 'model-cache');

        $this->app->singleton(CacheKeyGeneratorContract::class, CacheKeyGenerator::class);
        $this->app->singleton(CacheValueSerializer::class);
        $this->app->singleton(TagManager::class);
        $this->app->singleton(DependencyTracker::class);
        $this->app->singleton(ModelCache::class);

        $this->app->alias(ModelCache::class, 'model-cache');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/model-cache.php' => config_path('model-cache.php'),
        ], 'model-cache-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushModelCacheCommand::class,
                InspectModelCacheCommand::class,
                WarmModelCacheCommand::class,
            ]);
        }
    }
}
