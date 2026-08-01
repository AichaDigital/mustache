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
        $this->legacyAllowedModelsDetected = $result['legacy_allowed_models'];
    }

    /**
     * The warning must state: which keys were absent and what defaults now
     * apply, the effective mode, the report-only caveat, the exact edit
     * required, and a link to UPGRADE-3.md. It must NOT assert which file
     * the configuration came from — under cached config that cannot be
     * verified (spec §11.1).
     */
    protected function warnAboutIncompleteSecurityConfig(): void
    {
        /** @var string $mode */
        $mode = $this->app->make('config')->get('mustache-resolver.security.mode', SecurityValidator::MODE_ENFORCE);

        $context = [
            'absent_keys_filled_with_v3_defaults' => $this->absentSecurityKeys,
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
            /** @var array<string, mixed> $security */
            $security = $app['config']['mustache-resolver']['security'] ?? [];

            // Limits throw; report must not change behaviour vs v2.1, so only
            // enforce wires them. off keeps the explicit "no checks" promise.
            if (($security['mode'] ?? SecurityValidator::MODE_ENFORCE) !== SecurityValidator::MODE_ENFORCE) {
                return new MustacheParser(maxTemplateLength: null, maxTokens: null);
            }

            /** @var array<string, mixed> $limits */
            $limits = $security['limits'] ?? [];

            return new MustacheParser(
                maxTemplateLength: array_key_exists('max_template_length', $limits)
                    ? $limits['max_template_length']
                    : MustacheParser::DEFAULT_MAX_TEMPLATE_LENGTH,
                maxTokens: array_key_exists('max_tokens', $limits)
                    ? $limits['max_tokens']
                    : MustacheParser::DEFAULT_MAX_TOKENS,
            );
        });
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
            /** @var array{allowed_root_models?: array<string>, blacklisted_attributes?: array<string>, blacklisted_patterns?: array<string>, max_depth?: int, mode?: string} $config */
            $config = $app['config']['mustache-resolver']['security'] ?? [];

            $mode = $config['mode'] ?? SecurityValidator::MODE_ENFORCE;

            // An invalid mode fails closed (enforce) rather than leaving the app unprotected
            if (! in_array($mode, [SecurityValidator::MODE_OFF, SecurityValidator::MODE_REPORT, SecurityValidator::MODE_ENFORCE], true)) {
                Log::warning('mustache-resolver: invalid security.mode, falling back to "enforce"', ['mode' => $mode]);
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
                maxDepth: $config['max_depth'] ?? 10,
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
