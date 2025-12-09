<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\ResolverInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Base class for resolvers providing common functionality.
 */
abstract class AbstractResolver implements ResolverInterface
{
    /**
     * Token types this resolver handles.
     *
     * @return TokenType[]
     */
    abstract protected function supportedTypes(): array;

    public function supports(TokenInterface $token, ContextInterface $context): bool
    {
        return in_array($token->getType(), $this->supportedTypes(), true);
    }

    abstract public function resolve(TokenInterface $token, ContextInterface $context): mixed;

    public function priority(): int
    {
        return 50;
    }

    abstract public function name(): string;

    /**
     * Navigate a dot-notation path on the context accessor.
     *
     * @param  array<int, string>  $path
     */
    protected function navigatePath(array $path, ContextInterface $context): mixed
    {
        $accessor = $context->getAccessor();
        $dotPath = implode('.', $path);

        return $accessor->get($dotPath);
    }

    /**
     * Check if a path would resolve to null.
     *
     * @param  array<int, string>  $path
     */
    protected function pathIsNull(array $path, ContextInterface $context): bool
    {
        return $this->navigatePath($path, $context) === null;
    }

    /**
     * Check if the token prefix matches the expected context prefix.
     */
    protected function prefixMatches(TokenInterface $token, ContextInterface $context): bool
    {
        $expectedPrefix = $context->getExpectedPrefix();

        if ($expectedPrefix === null) {
            return true;
        }

        return $token->getPrefix() === $expectedPrefix;
    }
}
