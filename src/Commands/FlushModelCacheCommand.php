<?php

namespace GhostCompiler\LaravelModelCaching\Commands;

use GhostCompiler\LaravelModelCaching\ModelCache;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class FlushModelCacheCommand extends Command
{
    protected $signature = 'model-cache:flush
        {model? : Fully-qualified model class}
        {id? : Model primary key}
        {--known : Flush every cache key tracked by this package}';

    protected $description = 'Flush model-cache entries by model instance or by the known dependency index.';

    public function handle(ModelCache $cache): int
    {
        if ($this->option('known') || ! $this->argument('model')) {
            $count = $cache->flushKnown();
            $this->info("Flushed {$count} known model-cache entries.");

            return self::SUCCESS;
        }

        $modelClass = (string) $this->argument('model');
        $id = $this->argument('id');

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("{$modelClass} is not an Eloquent model.");
        }

        if ($id === null) {
            $this->warn('Pass an id to flush a single model instance, or use --known to flush tracked entries.');

            return self::FAILURE;
        }

        /** @var Model $model */
        $model = new $modelClass;
        $model->setAttribute($model->getKeyName(), $id);
        $model->exists = true;

        $cache->invalidateModel($model);
        $this->info("Flushed cache entries depending on {$modelClass}:{$id}.");

        return self::SUCCESS;
    }
}
