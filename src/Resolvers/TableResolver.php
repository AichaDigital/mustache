<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Resolves direct table access patterns (snake_case prefix).
 *
 * Examples:
 *   {{users.email}} → Direct table query
 *   {{user_profiles.avatar}} → Direct table query
 *
 * Note: This resolver works with data already loaded in context.
 * For actual database queries, use a specialized TableQueryResolver.
 */
final class TableResolver extends AbstractResolver
{
    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::TABLE];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        $fieldPath = $token->getFieldPath();

        if (empty($fieldPath)) {
            return null;
        }

        // For table access, we include the prefix in the path
        // since tables are accessed by their full name
        $fullPath = $token->getPath();

        return $this->navigatePath($fullPath, $context);
    }

    public function priority(): int
    {
        return 10;
    }

    public function name(): string
    {
        return 'table';
    }
}
