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
     * Register the main resolver.
     */
    protected function registerResolver(): void
    {
        $this->app->singleton(MustacheResolver::class, function ($app) {
            return new MustacheResolver(
                $app->make(ParserInterface::class),
                $app->make(ResolutionPipeline::class),
                $app->make(CacheInterface::class),
            );
        });

        $this->app->alias(MustacheResolver::class, 'mustache');
    }
}
