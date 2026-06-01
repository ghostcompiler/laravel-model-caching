<?php

namespace GhostCompiler\LaravelModelCaching\Tests;

use GhostCompiler\LaravelModelCaching\ModelCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ModelCacheServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('model-cache.cache_tags', false);
    }
}
