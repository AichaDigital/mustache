<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

use AichaDigital\MustacheResolver\Contracts\TokenInterface;

/**
 * Exception thrown when token resolution fails.
 */
class ResolutionException extends MustacheException
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

    public static function forToken(TokenInterface $token, string $reason): self
    {
        return new self(
            "Failed to resolve token '{$token->getRaw()}': {$reason}",
            $token
        );
    }
}
