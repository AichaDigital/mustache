<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Resolves variable references from the context.
 *
 * Examples:
 *   {{$myVariable}} → context variable 'myVariable'
 *   {{$currentUser}} → context variable 'currentUser'
 *   {{$period}} → context variable 'period'
 */
final class VariableResolver extends AbstractResolver
{
    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::VARIABLE];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        $path = $token->getPath();

        if (empty($path)) {
            return null;
        }

        $variableName = $path[0];
        $variables = $context->getVariables();

        if (! array_key_exists($variableName, $variables)) {
            return null;
        }

        return $variables[$variableName];
    }

    public function priority(): int
    {
        return 60;
    }

    public function name(): string
    {
        return 'variable';
    }
}
