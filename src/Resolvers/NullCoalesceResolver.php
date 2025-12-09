<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Resolves null coalesce patterns.
 *
 * Examples:
 *   {{User.name ?? 'Anonymous'}} → $user->name or 'Anonymous'
 *   {{User.nickname ?? User.name}} → $user->nickname or $user->name
 *   {{config.value ?? 'default'}} → config value or 'default'
 */
final class NullCoalesceResolver extends AbstractResolver
{
    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::NULL_COALESCE];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        $fieldPath = $token->getFieldPath();

        // Try to resolve the primary path
        $value = null;

        if (! empty($fieldPath)) {
            $value = $this->navigatePath($fieldPath, $context);
        }

        // If value is null, use the default
        if ($value === null) {
            return $token->getDefaultValue();
        }

        return $value;
    }

    public function priority(): int
    {
        return 90;
    }

    public function name(): string
    {
        return 'null_coalesce';
    }
}
