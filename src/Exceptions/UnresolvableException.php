<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

use AichaDigital\MustacheResolver\Contracts\TokenInterface;

/**
 * Exception thrown when no resolver can handle a token.
 */
class UnresolvableException extends MustacheException
{
    public function __construct(
        string $message,
        private readonly ?TokenInterface $token = null,
    ) {
        parent::__construct($message);
    }

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    public static function forToken(TokenInterface $token): self
    {
        return new self(
            "No resolver found for token: {$token->getRaw()} (type: {$token->getType()->value})",
            $token
        );
    }
}
