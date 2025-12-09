<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Resolves simple Model.field patterns.
 *
 * Examples:
 *   {{User.name}} → $user->name
 *   {{User.email}} → $user->email
 *   {{CommandCenter.status}} → $commandCenter->status
 */
final class ModelResolver extends AbstractResolver
{
    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::MODEL];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        if (! $this->prefixMatches($token, $context)) {
            return null;
        }

        $fieldPath = $token->getFieldPath();

        if (empty($fieldPath)) {
            return null;
        }

        return $this->navigatePath($fieldPath, $context);
    }

    public function priority(): int
    {
        return 20;
    }

    public function name(): string
    {
        return 'model';
    }
}
