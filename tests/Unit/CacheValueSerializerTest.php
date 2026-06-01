<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Unit;

use Carbon\CarbonImmutable;
use GhostCompiler\LaravelModelCaching\Cache\CacheValueSerializer;
use GhostCompiler\LaravelModelCaching\Tests\Fixtures\Post;
use GhostCompiler\LaravelModelCaching\Tests\Fixtures\User;
use GhostCompiler\LaravelModelCaching\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CacheValueSerializerTest extends TestCase
{
    public function test_it_round_trips_a_model_with_relations(): void
    {
        $serializer = new CacheValueSerializer;

        $user = new User;
        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'Ada');
        $user->exists = true;
        $user->syncOriginal();

        $post = new Post;
        $post->setAttribute('id', 5);
        $post->setAttribute('user_id', 1);
        $post->exists = true;
        $post->syncOriginal();

        $user->setRelation('posts', new EloquentCollection([$post]));

        $stored = $serializer->prepareForStorage($user);
        $restored = $serializer->restoreFromStorage($stored);

        $this->assertInstanceOf(User::class, $restored);
        $this->assertSame(1, $restored->getKey());
        $this->assertTrue($restored->relationLoaded('posts'));
        $this->assertInstanceOf(EloquentCollection::class, $restored->getRelation('posts'));
        $this->assertInstanceOf(Post::class, $restored->getRelation('posts')->first());
    }

    public function test_it_round_trips_an_eloquent_collection(): void
    {
        $serializer = new CacheValueSerializer;

        $post = new Post;
        $post->setAttribute('id', 5);
        $post->exists = true;
        $post->syncOriginal();

        $collection = new EloquentCollection([$post]);
        $stored = $serializer->prepareForStorage($collection);
        $restored = $serializer->restoreFromStorage($stored);

        $this->assertInstanceOf(EloquentCollection::class, $restored);
        $this->assertCount(1, $restored);
        $this->assertInstanceOf(Post::class, $restored->first());
    }

    public function test_it_round_trips_models_with_carbon_original_through_json(): void
    {
        $serializer = new CacheValueSerializer;

        $user = new User;
        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'Ada');
        $user->setAttribute('created_at', '2024-01-01T00:00:00+00:00');
        $user->exists = true;
        $user->syncOriginal();
        $user->syncOriginalAttribute('created_at', CarbonImmutable::parse('2024-01-01T00:00:00+00:00'));

        $stored = $serializer->prepareForStorage($user);
        $jsonSafe = json_decode(json_encode($stored), true);

        $this->assertFalse($serializer->isCorrupt($jsonSafe));

        $restored = $serializer->restoreFromStorage($jsonSafe);

        $this->assertInstanceOf(User::class, $restored);
        $this->assertSame(1, $restored->getKey());
    }

    public function test_it_preserves_database_connection_name(): void
    {
        $serializer = new CacheValueSerializer;

        $user = new User;
        $user->setConnection('testing');
        $user->setAttribute('id', 1);
        $user->exists = true;
        $user->syncOriginal();

        $stored = $serializer->prepareForStorage($user);
        $restored = $serializer->restoreFromStorage($stored);

        $this->assertInstanceOf(User::class, $restored);
        $this->assertSame('testing', $restored->getConnectionName());
        $this->assertTrue($restored->exists);
    }

    public function test_it_detects_corrupt_cached_values(): void
    {
        $serializer = new CacheValueSerializer;

        $corrupt = unserialize('O:8:"stdClass":0:{}');
        $incomplete = @unserialize('O:21:"NotARealClassName":0:{}');

        $this->assertFalse($serializer->isCorrupt($corrupt));

        if (is_object($incomplete) && get_class($incomplete) === '__PHP_Incomplete_Class') {
            $this->assertTrue($serializer->isCorrupt($incomplete));
            $this->assertNull($serializer->restoreFromStorage($incomplete));
        } else {
            $this->markTestSkipped('Could not produce __PHP_Incomplete_Class in this PHP build.');
        }
    }
}
