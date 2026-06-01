<?php

return [
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Default TTL
    |--------------------------------------------------------------------------
    |
    | Used by findCached(), loadCached(), and command warmups when no explicit
    | TTL is provided. Query-level remember($ttl) always wins.
    |
    */
    'default_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Auto Remember
    |--------------------------------------------------------------------------
    |
    | When true, models using HasModelCaching cache read queries automatically
    | using default_ttl. Use dontCache() to opt out per chain. Explicit
    | remember($ttl) and rememberForever() still apply when set.
    |
    */
    'auto_remember' => false,

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Null means Laravel's default cache store. Redis is recommended for the
    | dependency index because it can use native sets.
    |
    */
    'store' => null,

    'prefix' => 'model-cache',

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    |
    | Enable tagged cache writes when the selected cache driver supports tags.
    | The dependency index still works when tags are unavailable.
    |
    */
    'cache_tags' => true,

    /*
    |--------------------------------------------------------------------------
    | Dependency Index
    |--------------------------------------------------------------------------
    */
    'dependency_ttl' => 604800,
    'use_redis_sets' => true,

    /*
    |--------------------------------------------------------------------------
    | Model Observers
    |--------------------------------------------------------------------------
    |
    | Models using HasModelCaching are automatically observed. On saved,
    | deleted, restored, or force deleted events, related cache entries are
    | invalidated through the dependency index.
    |
    */
    'auto_observe_models' => true,

    /*
    |--------------------------------------------------------------------------
    | Context
    |--------------------------------------------------------------------------
    |
    | Include auth, tenant, locale, or any other boundary in cache keys.
    | Callbacks receive no arguments and must return scalar/array JSON values.
    |
    */
    'include_auth_id' => false,
    'context_callbacks' => [
        // 'tenant' => fn () => app('tenant')->id,
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph Map Version
    |--------------------------------------------------------------------------
    |
    | Bump this value after changing Laravel's morph map aliases so old cache
    | entries cannot collide with new polymorphic meanings.
    |
    */
    'morph_map_version' => 1,

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */
    'debug' => false,
];
