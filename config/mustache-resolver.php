<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, unresolvable mustaches will throw an exception.
    | When disabled, they will be replaced with empty string or kept as-is
    | depending on the 'keep_unresolved' setting.
    |
    */
    'strict' => env('MUSTACHE_STRICT', true),

    /*
    |--------------------------------------------------------------------------
    | Keep Unresolved Mustaches
    |--------------------------------------------------------------------------
    |
    | When strict mode is disabled, this determines whether unresolved
    | mustaches are kept in the output (true) or replaced with empty string (false).
    |
    */
    'keep_unresolved' => env('MUSTACHE_KEEP_UNRESOLVED', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for parsed templates. Set 'enabled' to true to
    | enable caching of parsed token structures.
    |
    */
    'cache' => [
        'enabled' => env('MUSTACHE_CACHE_ENABLED', false),
        'store' => env('MUSTACHE_CACHE_STORE', null), // null = default cache store
        'ttl' => env('MUSTACHE_CACHE_TTL', 3600), // seconds
        'prefix' => 'mustache_resolver_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Resolvers
    |--------------------------------------------------------------------------
    |
    | Register custom resolver classes here. They must implement
    | AichaDigital\MustacheResolver\Contracts\ResolverInterface
    |
    | Example:
    | 'resolvers' => [
    |     \App\Resolvers\CustomResolver::class,
    | ],
    |
    */
    'resolvers' => [],

    /*
    |--------------------------------------------------------------------------
    | Excluded Resolvers
    |--------------------------------------------------------------------------
    |
    | List resolver names to exclude from the pipeline.
    | Built-in resolver names: model, table, relation, dynamic, collection,
    | function, math, variable, null_coalesce
    |
    */
    'excluded_resolvers' => [],

    /*
    |--------------------------------------------------------------------------
    | Registered Functions
    |--------------------------------------------------------------------------
    |
    | Register custom functions that can be used in mustache templates.
    | Each entry should be 'function_name' => callable or class string.
    |
    | Example:
    | 'functions' => [
    |     'currency' => fn($value) => number_format($value, 2) . ' EUR',
    |     'slugify' => [\App\Helpers\StringHelper::class, 'slugify'],
    | ],
    |
    */
    'functions' => [],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security restrictions for the resolver.
    |
    */
    'security' => [
        // Restrict which model classes can be accessed
        'allowed_models' => [], // Empty = all allowed

        // Restrict which table names can be accessed
        'allowed_tables' => [], // Empty = all allowed

        // Maximum nesting depth for relation chains
        'max_depth' => 10,

        // Disallow access to certain attributes/columns
        'blacklisted_attributes' => [
            'password',
            'remember_token',
            'api_token',
            'secret',
        ],
    ],
];
