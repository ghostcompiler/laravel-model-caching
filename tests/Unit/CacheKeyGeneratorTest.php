<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Unit;

use GhostCompiler\LaravelModelCaching\Contracts\CacheKeyGenerator;
use GhostCompiler\LaravelModelCaching\Tests\Fixtures\User;
use GhostCompiler\LaravelModelCaching\Tests\TestCase;

class CacheKeyGeneratorTest extends TestCase
{
    public function test_nested_eager_loads_generate_distinct_keys(): void
    {
        $keys = app(CacheKeyGenerator::class);

        $posts = $keys->generate(User::query()->with('posts'), 'get', ['columns' => ['*']]);
        $nested = $keys->generate(User::query()->with('posts.comments'), 'get', ['columns' => ['*']]);
        $many = $keys->generate(User::query()->with(['posts', 'roles']), 'get', ['columns' => ['*']]);

        $this->assertNotSame($posts, $nested);
        $this->assertNotSame($posts, $many);
        $this->assertNotSame($nested, $many);
    }

    public function test_pagination_state_changes_cache_key(): void
    {
        $keys = app(CacheKeyGenerator::class);

        $pageOne = $keys->generate(User::query()->with('posts'), 'paginate', [
            'perPage' => 10,
            'pageName' => 'page',
            'page' => 1,
        ]);

        $pageTwo = $keys->generate(User::query()->with('posts'), 'paginate', [
            'perPage' => 10,
            'pageName' => 'page',
            'page' => 2,
        ]);

        $this->assertNotSame($pageOne, $pageTwo);
    }
}
