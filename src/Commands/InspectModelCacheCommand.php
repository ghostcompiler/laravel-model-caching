<?php

namespace GhostCompiler\LaravelModelCaching\Commands;

use GhostCompiler\LaravelModelCaching\Cache\DependencyTracker;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class InspectModelCacheCommand extends Command
{
    protected $signature = 'model-cache:inspect
        {model : Fully-qualified model class}
        {id : Model primary key}';

    protected $description = 'List cache keys currently depending on a model instance.';

    public function handle(DependencyTracker $dependencies): int
    {
        $modelClass = (string) $this->argument('model');
        $id = $this->argument('id');

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("{$modelClass} is not an Eloquent model.");
        }

        /** @var Model $model */
        $model = new $modelClass;
        $model->setAttribute($model->getKeyName(), $id);
        $model->exists = true;

        $keys = $dependencies->cacheKeysForModel($model);

        if ($keys === []) {
            $this->line('No tracked cache keys found.');

            return self::SUCCESS;
        }

        $this->table(['Cache key'], array_map(fn (string $key) => [$key], $keys));

        return self::SUCCESS;
    }
}
