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
        // Enforcement mode:
        // - 'off': no checks are applied
        // - 'report': violations are logged (Log::warning) but resolution proceeds
        // - 'enforce': violations block resolution / model access (v3 default)
        // Fresh installs get 'enforce'. A config block published under v2
        // keeps the mode it declares — see UPGRADE-3.md.
        'mode' => env('MUSTACHE_SECURITY_MODE', 'enforce'),

        // Restrict which Eloquent model classes may be used as the ROOT data
        // source. Fully-qualified class names only (::class). Empty = all
        // allowed — this is opt-in hardening, not the primary barrier.
        // It validates the root model only: a non-blacklisted attribute of a
        // NESTED model resolves even when its class is absent from this list
        // (nested serialization is covered by the container policy instead).
        'allowed_root_models' => [],

        // Maximum nesting depth for relation chains. Always a number —
        // there is no "unlimited" here; null (or an absent key) means the
        // default below. A numeric string is accepted and cast.
        'max_depth' => 10,

        // Whole-container serialization (a token resolving to an array or
        // an Eloquent Collection, e.g. {{User.posts}}) is blocked by
        // default in enforce mode. Setting this to true authorises plain
        // arrays and Collections to serialize whole (still filtered by
        // blacklisted_attributes below). It does NOT authorise Eloquent
        // models: a bare Model always needs AichaDigital\MustacheResolver\
        // Contracts\SafeForTemplateSerialization on its own class, never
        // this global flag — see OutputSanitizer::maySerialiseWhole().
        'allow_container_serialization' => env('MUSTACHE_SECURITY_ALLOW_CONTAINER_SERIALIZATION', false),

        // Disallow access to certain attributes/columns.
        // Every segment of a dot-notation path is checked, so a blacklisted
        // attribute is also blocked behind a relation (e.g. User.relationship.password).
        'blacklisted_attributes' => [
            'password',
            'remember_token',
            'api_token',
            'secret',
        ],

        // Attribute name patterns blocked in addition to the exact names above.
        // Glob-style (Str::is), case-insensitive, applied to every path segment.
        // Catches real-world renames of sensitive fields (auth_token, stripe_key,
        // password_plain, ...). Expect occasional false positives (public_key,
        // sort_key): they are visible in the log and removable here.
        // An explicit [] disables pattern matching deliberately.
        'blacklisted_patterns' => [
            '*_token',
            '*_secret',
            '*_key',
            '*password*',
            '*_hash',
            'otp',
            'pin',
            'cvv',
        ],

        // Parse-time ceilings guarding resolution amplification (a template
        // with many relation paths multiplies lazy queries). Applied only when
        // mode is 'enforce' — a new throw in report mode would break the
        // "report changes nothing" invariant.
        //
        // max_template_length is measured in BYTES (strlen), not characters.
        // null, an empty value (MUSTACHE_SECURITY_MAX_TOKENS= in .env) or
        // false all mean unlimited; a numeric string coming from .env is
        // cast, never left as a string — see MustacheServiceProvider::
        // normalizeLimit(). A value that is neither falls back to the
        // default and is logged. An explicit 0 means literally zero — in
        // enforce mode it rejects EVERY template. It is NOT the "disable"
        // convention some tools use; to lift a ceiling use null or empty.
        'limits' => [
            'max_template_length' => env('MUSTACHE_SECURITY_MAX_TEMPLATE_LENGTH', 100000),
            'max_tokens' => env('MUSTACHE_SECURITY_MAX_TOKENS', 1000),
        ],
    ],
];
