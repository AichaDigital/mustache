<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Pipeline;

use AichaDigital\MustacheResolver\Contracts\ResolverInterface;
use AichaDigital\MustacheResolver\Resolvers\CollectionResolver;
use AichaDigital\MustacheResolver\Resolvers\DynamicFieldResolver;
use AichaDigital\MustacheResolver\Resolvers\ModelResolver;
use AichaDigital\MustacheResolver\Resolvers\NullCoalesceResolver;
use AichaDigital\MustacheResolver\Resolvers\RelationResolver;
use AichaDigital\MustacheResolver\Resolvers\TableResolver;
use AichaDigital\MustacheResolver\Resolvers\TemporalResolver;
use AichaDigital\MustacheResolver\Resolvers\VariableResolver;

/**
 * Builder for creating configured resolution pipelines.
 */
final class PipelineBuilder
{
    /**
     * @var ResolverInterface[]
     */
    private array $resolvers = [];

    private bool $includeDefaults = true;

    /**
     * @var string[]
     */
    private array $excludedResolvers = [];

    public static function create(): self
    {
        return new self;
    }

    /**
     * Include default resolvers (default behavior).
     */
    public function withDefaults(): self
    {
        $this->includeDefaults = true;

        return $this;
    }

    /**
     * Exclude all default resolvers.
     */
    public function withoutDefaults(): self
    {
        $this->includeDefaults = false;

        return $this;
    }

    /**
     * Exclude specific resolvers by name.
     */
    public function exclude(string ...$resolverNames): self
    {
        $this->excludedResolvers = array_merge($this->excludedResolvers, $resolverNames);

        return $this;
    }

    /**
     * Add a custom resolver.
     */
    public function addResolver(ResolverInterface $resolver): self
    {
        $this->resolvers[] = $resolver;

        return $this;
    }

    /**
     * Build the configured pipeline.
     */
    public function build(): ResolutionPipeline
    {
        $allResolvers = [];

        if ($this->includeDefaults) {
            $allResolvers = $this->getDefaultResolvers();
        }

        foreach ($this->resolvers as $resolver) {
            $allResolvers[] = $resolver;
        }

        // Filter excluded resolvers
        $allResolvers = array_filter(
            $allResolvers,
            fn (ResolverInterface $r) => ! in_array($r->name(), $this->excludedResolvers, true)
        );

        return new ResolutionPipeline(array_values($allResolvers));
    }

    /**
     * Get the default set of resolvers.
     *
     * @return ResolverInterface[]
     */
    private function getDefaultResolvers(): array
    {
        return [
            new TemporalResolver,       // Priority 90 - TEMPORAL:, NOW:, TODAY:
            new NullCoalesceResolver,   // Priority 90 - Handle ?? first
            new VariableResolver,       // Priority 60 - $variables
            new DynamicFieldResolver,   // Priority 50 - $relation.field
            new CollectionResolver,     // Priority 40 - array[0] access
            new RelationResolver,       // Priority 30 - relation.field
            new ModelResolver,          // Priority 20 - Model.field
            new TableResolver,          // Priority 10 - table.field
        ];
    }
}
