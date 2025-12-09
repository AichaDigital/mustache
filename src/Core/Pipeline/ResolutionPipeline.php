<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Pipeline;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\ResolverInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Exceptions\UnresolvableException;

/**
 * Orchestrates the resolution of tokens through a chain of resolvers.
 */
final class ResolutionPipeline
{
    /**
     * @var ResolverInterface[]
     */
    private array $resolvers = [];

    /**
     * @param  ResolverInterface[]  $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $resolver) {
            $this->addResolver($resolver);
        }
        $this->sortByPriority();
    }

    /**
     * Add a resolver to the pipeline.
     */
    public function addResolver(ResolverInterface $resolver): self
    {
        $this->resolvers[] = $resolver;
        $this->sortByPriority();

        return $this;
    }

    /**
     * Resolve a token using the appropriate resolver.
     *
     * @throws UnresolvableException
     */
    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($token, $context)) {
                return $resolver->resolve($token, $context);
            }
        }

        throw UnresolvableException::forToken($token);
    }

    /**
     * Check if any resolver can handle the token.
     */
    public function canResolve(TokenInterface $token, ContextInterface $context): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($token, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the resolver that would handle a token.
     */
    public function getResolverFor(TokenInterface $token, ContextInterface $context): ?ResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($token, $context)) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Get all registered resolvers.
     *
     * @return ResolverInterface[]
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }

    /**
     * Get resolver names.
     *
     * @return string[]
     */
    public function getResolverNames(): array
    {
        return array_map(
            fn (ResolverInterface $resolver) => $resolver->name(),
            $this->resolvers
        );
    }

    /**
     * Sort resolvers by priority (highest first).
     */
    private function sortByPriority(): void
    {
        usort(
            $this->resolvers,
            fn (ResolverInterface $a, ResolverInterface $b) => $b->priority() <=> $a->priority()
        );
    }
}
