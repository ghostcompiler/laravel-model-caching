<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Unit;

use GhostCompiler\LaravelModelCaching\Tests\Fixtures\User;
use GhostCompiler\LaravelModelCaching\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CachedEloquentBuilderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('model-cache.auto_remember', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function seedUser(): User
    {
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Ada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->find(1);
    }

    public function test_creating_a_model_invalidates_cached_paginated_lists(): void
    {
        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::query()->remember(60)->orderBy('id')->paginate(10);
        $afterFirstPage = $queryCount;

        User::query()->remember(60)->orderBy('id')->paginate(10);
        $this->assertSame($afterFirstPage, $queryCount);

        $user = new User;
        $user->setAttribute('name', 'Grace');
        $user->save();

        User::query()->remember(60)->orderBy('id')->paginate(10);
        $this->assertGreaterThan($afterFirstPage, $queryCount);
    }

    public function test_remember_first_or_fail_caches_second_call(): void
    {
        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::query()->remember(60)->whereKey(1)->firstOrFail();
        $this->assertSame(1, $queryCount);

        User::query()->remember(60)->whereKey(1)->firstOrFail();
        $this->assertSame(1, $queryCount);
    }

    public function test_remember_first_caches_second_call(): void
    {
        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::query()->remember(60)->whereKey(1)->first();
        $this->assertSame(1, $queryCount);

        User::query()->remember(60)->whereKey(1)->first();
        $this->assertSame(1, $queryCount);
    }

    public function test_auto_remember_caches_without_explicit_remember(): void
    {
        config(['model-cache.auto_remember' => true]);

        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::query()->whereKey(1)->first();
        $this->assertSame(1, $queryCount);

        User::query()->whereKey(1)->first();
        $this->assertSame(1, $queryCount);
    }

    public function test_dont_cache_disables_auto_remember(): void
    {
        config(['model-cache.auto_remember' => true]);

        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::query()->dontCache()->whereKey(1)->first();
        User::query()->dontCache()->whereKey(1)->first();

        $this->assertSame(2, $queryCount);
    }

    public function test_find_cached_caches_second_call(): void
    {
        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::findCached(1);
        $this->assertSame(1, $queryCount);

        User::findCached(1);
        $this->assertSame(1, $queryCount);
    }

    public function test_without_remember_or_auto_remember_queries_each_time(): void
    {
        config(['model-cache.auto_remember' => false]);

        $this->seedUser();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        User::query()->whereKey(1)->first();
        User::query()->whereKey(1)->first();

        $this->assertSame(2, $queryCount);
    }
}
