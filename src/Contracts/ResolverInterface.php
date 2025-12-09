<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

use AichaDigital\MustacheResolver\Exceptions\ResolutionException;

interface ResolverInterface
{
    /**
     * Determine if this resolver can handle the given token.
     *
     * This method should be fast and lightweight, as it's called
     * for every resolver in the pipeline until one returns true.
     */
    public function supports(TokenInterface $token, ContextInterface $context): bool;

    /**
     * Resolve the token to its value.
     *
     * @throws ResolutionException If resolution fails and cannot recover
     */
    public function resolve(TokenInterface $token, ContextInterface $context): mixed;

    /**
     * Get the priority of this resolver.
     *
     * Higher priority resolvers are tried first.
     * Built-in resolvers use priorities 0-100.
     * Custom resolvers should use priorities above 100.
     */
    public function priority(): int;

    /**
     * Get a unique identifier for this resolver.
     */
    public function name(): string;
}
