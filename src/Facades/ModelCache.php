<?php

namespace GhostCompiler\LaravelModelCaching\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void invalidateModel(\Illuminate\Database\Eloquent\Model $model)
 * @method static void invalidateClassQueries(\Illuminate\Database\Eloquent\Model $model)
 * @method static int flushKnown()
 */
class ModelCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'model-cache';
    }
}
