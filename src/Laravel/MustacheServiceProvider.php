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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/mustache-resolver.php',
            'mustache-resolver'
        );

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
        $this->app->singleton(ParserInterface::class, function () {
            return new MustacheParser;
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
            /** @var array{allowed_models?: array<string>, blacklisted_attributes?: array<string>, max_depth?: int, mode?: string} $config */
            $config = $app['config']['mustache-resolver']['security'] ?? [];

            $mode = $config['mode'] ?? SecurityValidator::MODE_REPORT;

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
                allowedModels: $config['allowed_models'] ?? [],
                blacklistedAttributes: $config['blacklisted_attributes'] ?? [],
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
