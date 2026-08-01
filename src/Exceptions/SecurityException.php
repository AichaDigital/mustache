<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Exception thrown when a security violation is detected.
 */
class SecurityException extends MustacheException
{
    public static function unregisteredFunction(string $functionName): self
    {
        return new self("Function not registered: {$functionName}");
    }

    public static function restrictedPath(string $path): self
    {
        return new self("Access to path '{$path}' is restricted");
    }

    public static function dangerousExpression(string $expression): self
    {
        return new self("Expression contains dangerous patterns: {$expression}");
    }

    public static function templateTooLong(int $length, int $max): self
    {
        return new self("Template length {$length} exceeds the configured maximum of {$max} characters");
    }

    public static function tooManyTokens(int $count, int $max): self
    {
        return new self("Template contains {$count} mustache tokens, exceeding the configured maximum of {$max}");
    }
}
