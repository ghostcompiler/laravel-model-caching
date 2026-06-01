<?php

namespace GhostCompiler\LaravelModelCaching\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface CacheKeyGenerator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function generate(Builder $builder, string $operation, array $payload = []): string;
}
