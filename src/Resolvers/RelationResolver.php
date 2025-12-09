<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;

/**
 * Resolves Model.relation.field patterns with deep navigation.
 *
 * Examples:
 *   {{User.department.name}} → $user->department->name
 *   {{Post.author.email}} → $post->author->email
 *   {{Order.customer.address.city}} → $order->customer->address->city
 */
final class RelationResolver extends AbstractResolver
{
    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::RELATION];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        if (! $this->prefixMatches($token, $context)) {
            return null;
        }

        $fieldPath = $token->getFieldPath();

        if (count($fieldPath) < 2) {
            return null;
        }

        return $this->navigatePath($fieldPath, $context);
    }

    public function priority(): int
    {
        return 30;
    }

    public function name(): string
    {
        return 'relation';
    }
}
