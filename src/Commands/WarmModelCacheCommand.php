<?php

namespace GhostCompiler\LaravelModelCaching\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class WarmModelCacheCommand extends Command
{
    protected $signature = 'model-cache:warm
        {model : Fully-qualified model class}
        {--with=* : Relation to eager load, can be repeated}
        {--ttl= : TTL in seconds}
        {--limit=1000 : Maximum records to warm}';

    protected $description = 'Warm a cached model query with optional eager-loaded relations.';

    public function handle(): int
    {
        $modelClass = (string) $this->argument('model');

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("{$modelClass} is not an Eloquent model.");
        }

        $ttl = $this->option('ttl') !== null
            ? (int) $this->option('ttl')
            : (int) config('model-cache.default_ttl', 3600);

        $limit = max(1, (int) $this->option('limit'));
        $relations = array_filter((array) $this->option('with'));

        /** @var Model $model */
        $model = new $modelClass;
        $query = $modelClass::query()
            ->with($relations)
            ->limit($limit)
            ->remember($ttl);

        $count = $query->get()->count();

        $this->info("Warmed {$count} {$model->getTable()} records for {$modelClass}.");

        return self::SUCCESS;
    }
}
