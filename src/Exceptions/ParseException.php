<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Exception thrown when parsing a template fails.
 */
class ParseException extends MustacheException
{
    public function __construct(
        string $message,
        private readonly ?string $template = null,
        private readonly ?int $position = null,
    ) {
        parent::__construct($message);
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }
}
