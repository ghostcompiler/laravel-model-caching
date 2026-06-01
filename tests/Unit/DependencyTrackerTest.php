<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Unit;

use GhostCompiler\LaravelModelCaching\Cache\DependencyTracker;
use GhostCompiler\LaravelModelCaching\Tests\Fixtures\Post;
use GhostCompiler\LaravelModelCaching\Tests\Fixtures\User;
use GhostCompiler\LaravelModelCaching\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class DependencyTrackerTest extends TestCase
{
    public function test_it_indexes_loaded_related_models(): void
    {
        $tracker = app(DependencyTracker::class);

        $user = new User;
        $user->setAttribute('id', 1);
        $user->exists = true;

        $post = new Post;
        $post->setAttribute('id', 5);
        $post->exists = true;

        $user->setRelation('posts', new Collection([$post]));

        Cache::put('model-cache:test-key', $user, 60);
        $tracker->track('model-cache:test-key', $user, [], 60);

        $this->assertSame(['model-cache:test-key'], $tracker->cacheKeysForModel($post));
    }

    public function test_it_indexes_class_level_dependencies_for_query_model(): void
    {
        $tracker = app(DependencyTracker::class);

        $user = new User;
        $user->setAttribute('id', 1);
        $user->exists = true;

        $tracker->track('model-cache:list-key', $user, [], 60, $user);

        $this->assertSame(['model-cache:list-key'], $tracker->cacheKeysForClass(User::class));
    }

    public function test_it_invalidates_class_level_query_caches(): void
    {
        $tracker = app(DependencyTracker::class);

        $user = new User;
        $user->setAttribute('id', 1);
        $user->exists = true;

        Cache::put('model-cache:list-key', $user, 60);
        $tracker->track('model-cache:list-key', $user, [], 60, $user);

        $this->assertTrue(Cache::has('model-cache:list-key'));

        $tracker->invalidateClassQueries(User::class);

        $this->assertFalse(Cache::has('model-cache:list-key'));
    }
}
