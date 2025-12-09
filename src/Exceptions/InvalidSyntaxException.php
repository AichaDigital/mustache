<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Exception thrown when mustache syntax is invalid.
 */
class InvalidSyntaxException extends ParseException
{
    public static function unclosedMustache(string $template, int $position): self
    {
        return new self(
            "Unclosed mustache starting at position {$position}",
            $template,
            $position
        );
    }

    public static function emptyMustache(string $template, int $position): self
    {
        return new self(
            "Empty mustache at position {$position}",
            $template,
            $position
        );
    }

    public static function nestedMustache(string $template, int $position): self
    {
        return new self(
            "Nested mustache braces at position {$position}",
            $template,
            $position
        );
    }
}
