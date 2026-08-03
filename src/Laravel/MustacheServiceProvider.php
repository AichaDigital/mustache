<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Laravel;

use AichaDigital\MustacheResolver\Cache\ArrayCache;
use AichaDigital\MustacheResolver\Cache\NullCache;
use AichaDigital\MustacheResolver\Contracts\CacheInterface;
use AichaDigital\MustacheResolver\Contracts\ParserInterface;
use AichaDigital\MustacheResolver\Core\MustacheResolver;
use AichaDigital\MustacheResolver\Core\Parser\MustacheParser;
use AichaDigital\MustacheResolver\Core\Pipeline\PipelineBuilder;
use AichaDigital\MustacheResolver\Core\Pipeline\ResolutionPipeline;
use AichaDigital\MustacheResolver\Core\Security\OutputSanitizer;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class MustacheServiceProvider extends ServiceProvider
{
    /** @var list<string> */
    private array $absentSecurityKeys = [];

    /** @var array<string, mixed> */
    private array $appliedSecurityDefaults = [];

    private bool $legacyAllowedModelsDetected = false;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/mustache-resolver.php',
            'mustache-resolver'
        );

        $this->reconcileSecurityConfig();

        $this->registerCache();
        $this->registerParser();
        $this->registerPipeline();
        $this->registerSecurity();
        $this->registerResolver();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/mustache-resolver.php' => config_path('mustache-resolver.php'),
            ], 'mustache-resolver-config');
        }

        if ($this->absentSecurityKeys !== [] || $this->legacyAllowedModelsDetected) {
            $this->warnAboutIncompleteSecurityConfig();
        }
    }

    /**
     * Runs on every request on purpose: under a config cache built before the
     * upgrade, mergeConfigFrom() is skipped entirely and the cached block is
     * the consumer's v2 file — this is the only place absence can be detected.
     */
    protected function reconcileSecurityConfig(): void
    {
        $config = $this->app->make('config');

        /** @var array<string, mixed> $security */
        $security = $config->get('mustache-resolver.security', []);

        $result = SecurityConfigReconciler::reconcile($security);

        $config->set('mustache-resolver.security', $result['security']);
        $this->absentSecurityKeys = $result['absent'];
        $this->appliedSecurityDefaults = $result['applied'];
        $this->legacyAllowedModelsDetected = $result['legacy_allowed_models'];
    }

    /**
     * The warning must state: which keys were absent and what defaults now
     * apply, the effective mode, the report-only caveat, the exact edit
     * required, and a link to UPGRADE-3.md. It must NOT assert which file
     * the configuration came from — under cached config that cannot be
     * verified (spec §11.1).
     *
     * "What defaults now apply" means the VALUES, not just the key names:
     * `applied_v3_defaults` carries key => value so the reader can see that
     * mode is now enforce and which patterns are live without leaving the
     * log line to go and read the package source.
     */
    protected function warnAboutIncompleteSecurityConfig(): void
    {
        /** @var string $mode */
        $mode = $this->app->make('config')->get('mustache-resolver.security.mode', SecurityValidator::MODE_ENFORCE);

        $context = [
            'absent_keys_filled_with_v3_defaults' => $this->absentSecurityKeys,
            'applied_v3_defaults' => $this->appliedSecurityDefaults,
            'effective_mode' => $mode,
            'action' => 'Add the listed keys to your published mustache-resolver.php config (or re-publish it), then review UPGRADE-3.md.',
        ];

        if ($this->legacyAllowedModelsDetected) {
            $context['renamed_key'] = 'security.allowed_models is now security.allowed_root_models '
                .'(FQCN-only, validates the root model only). Its value was carried over; rename the key.';
        }

        if ($mode === SecurityValidator::MODE_REPORT) {
            $context['report_mode'] = 'Under report mode the v3 protections only report, they do not block.';
        }

        Log::warning(
            'mustache-resolver: security configuration is missing v3 keys; defaults were applied for this runtime',
            $context,
        );
    }

    /**
     * Register the cache.
     */
    protected function registerCache(): void
    {
        $this->app->singleton(CacheInterface::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = $app['config']['mustache-resolver'];

            if (! ($config['cache']['enabled'] ?? false)) {
                return new NullCache;
            }

            return new ArrayCache;
        });
    }

    /**
     * Register the parser.
     */
    protected function registerParser(): void
    {
        $this->app->singleton(ParserInterface::class, function ($app) {
            // The effective mode is resolved ONCE, by the validator, and read
            // back from it here. Re-deriving it from raw config was the split
            // that let an invalid mode (a typo) leave the parser unlimited
            // while the validator, reading the same value, failed closed to
            // enforce — two divergent answers to one question.
            $mode = $app->make(SecurityValidator::class)->getMode();

            // Limits throw; report must not change behaviour vs v2.1, so only
            // enforce wires them. off keeps the explicit "no checks" promise.
            if ($mode !== SecurityValidator::MODE_ENFORCE) {
                return new MustacheParser(maxTemplateLength: null, maxTokens: null);
            }

            /** @var array<string, mixed> $limits */
            $limits = $app['config']['mustache-resolver']['security']['limits'] ?? [];

            return new MustacheParser(
                maxTemplateLength: self::normalizeLimit(
                    $limits, 'max_template_length', MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH
                ),
                maxTokens: self::normalizeLimit(
                    $limits, 'max_tokens', MustacheParser::DEFAULT_MAX_TOKENS
                ),
            );
        });
    }

    /**
     * Normalise a parse-time ceiling into what MustacheParser accepts (?int).
     *
     * Both ceilings are published as env() reads, and env() returns STRINGS.
     * Passing one straight into the parser's ?int constructor under
     * declare(strict_types=1) is a TypeError at container-resolution time —
     * setting either documented variable in .env crashed the application
     * rather than tightening a limit.
     *
     * Absent  → the v3 default (the key was never configured).
     * null, '' or false → unlimited. Empty is what `KEY=` in a .env yields
     *   and false what `KEY=false` yields; both read as "no ceiling", never
     *   as the 0 an unguarded (int) cast would have produced — 0 would
     *   reject every template, turning a disable into a total outage.
     * numeric → its integer value (an explicit 0 is left alone: it is a
     *   deliberate value, not the artefact of a cast).
     * anything else → the v3 default, warned. Failing closed on a value
     *   nobody can interpret matches how an invalid security.mode is handled.
     *
     * @param  array<string, mixed>  $limits
     */
    private static function normalizeLimit(array $limits, string $key, int $default): ?int
    {
        if (! array_key_exists($key, $limits)) {
            return $default;
        }

        $value = $limits[$key];

        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        Log::warning('mustache-resolver: invalid security.limits value, falling back to the default', [
            'key' => 'security.limits.'.$key,
            'type' => get_debug_type($value),
            'default' => $default,
        ]);

        return $default;
    }

    /**
     * Normalise max_depth into the int SecurityValidator requires.
     *
     * Same class of defect as normalizeLimit(): the value went into an int
     * parameter uncast, so a string (a consumer wiring it to env(), the way
     * the limits are published) was a TypeError. Unlike the ceilings there
     * is no "unlimited" here — max_depth is always a number — so an absent
     * key and an explicit null both mean the default, which is what the ??
     * this replaces already did and what the reconciler records as absent.
     */
    private static function normalizeDepth(mixed $value, int $default = 10): int
    {
        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        Log::warning('mustache-resolver: invalid security.max_depth, falling back to the default', [
            'type' => get_debug_type($value),
            'default' => $default,
        ]);

        return $default;
    }

    /**
     * Register the resolution pipeline.
     */
    protected function registerPipeline(): void
    {
        $this->app->singleton(ResolutionPipeline::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = $app['config']['mustache-resolver'];

            $builder = PipelineBuilder::create();

            // Exclude configured resolvers
            if (! empty($config['excluded_resolvers'])) {
                $builder->exclude(...$config['excluded_resolvers']);
            }

            // Add custom resolvers
            if (! empty($config['resolvers'])) {
                foreach ($config['resolvers'] as $resolverClass) {
                    $builder->addResolver($app->make($resolverClass));
                }
            }

            return $builder->build();
        });
    }

    /**
     * Register the security validator.
     */
    protected function registerSecurity(): void
    {
        $this->app->singleton(SecurityValidator::class, function ($app) {
            /** @var array{allowed_root_models?: array<string>, blacklisted_attributes?: array<string>, blacklisted_patterns?: array<string>, max_depth?: mixed, mode?: mixed} $config */
            $config = $app['config']['mustache-resolver']['security'] ?? [];

            // THE effective-mode resolution for the whole package: the parser
            // reads it back off this instance rather than repeating it.
            $mode = $config['mode'] ?? SecurityValidator::MODE_ENFORCE;

            // An invalid mode fails closed (enforce) rather than leaving the app unprotected
            if (! in_array($mode, [SecurityValidator::MODE_OFF, SecurityValidator::MODE_REPORT, SecurityValidator::MODE_ENFORCE], true)) {
                Log::warning('mustache-resolver: invalid security.mode, falling back to "enforce"', [
                    'mode' => is_scalar($mode) ? $mode : get_debug_type($mode),
                ]);
                $mode = SecurityValidator::MODE_ENFORCE;
            }

            // Dedupe repeated reports (e.g. batch translations or has()+get()
            // sequences) so a crafted template cannot flood the logs
            $reported = [];

            // Keep the dedupe state scoped to the request/job cycle: in
            // Octane or queue workers a singleton closure would otherwise
            // accumulate paths forever and suppress reports from later
            // requests or jobs
            $resetReported = function () use (&$reported): void {
                $reported = [];
            };

            $app->terminating($resetReported);

            if ($app->bound('queue')) {
                $app['queue']->looping($resetReported);
            }

            return new SecurityValidator(
                allowedRootModels: $config['allowed_root_models'] ?? [],
                blacklistedAttributes: array_key_exists('blacklisted_attributes', $config)
                    ? $config['blacklisted_attributes']
                    : SecurityValidator::DEFAULT_BLACKLISTED_ATTRIBUTES,
                blacklistedPatterns: array_key_exists('blacklisted_patterns', $config)
                    ? $config['blacklisted_patterns']
                    : SecurityValidator::DEFAULT_BLACKLISTED_PATTERNS,
                maxDepth: self::normalizeDepth($config['max_depth'] ?? null),
                mode: $mode,
                reporter: function (string $message, array $context = []) use (&$reported) {
                    $key = $message.'|'.($context['path'] ?? $context['model'] ?? '');

                    if (isset($reported[$key])) {
                        return;
                    }

                    // Bound memory within a single cycle
                    if (count($reported) >= 1000) {
                        $reported = [];
                    }

                    $reported[$key] = true;
                    Log::warning($message, $context);
                },
            );
        });
    }

    /**
     * Register the main resolver.
     */
    protected function registerResolver(): void
    {
        $this->app->singleton(MustacheResolver::class, function ($app) {
            return new MustacheResolver(
                $app->make(ParserInterface::class),
                $app->make(ResolutionPipeline::class),
                $app->make(CacheInterface::class),
                $app->make(SecurityValidator::class),
                new OutputSanitizer(
                    $app->make(SecurityValidator::class),
                    (bool) ($app['config']['mustache-resolver']['security']['allow_container_serialization'] ?? false),
                ),
            );
        });

        $this->app->alias(MustacheResolver::class, 'mustache');
    }
}
