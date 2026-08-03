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

    /**
     * The ceiling is measured in BYTES, and the message says so.
     *
     * MustacheParser guards this with strlen(), which counts bytes; saying
     * "characters" made the limit look encoding-dependent and understated it
     * for any multi-byte template — a consumer computing their ceiling from
     * a character count would set it too high. Bytes is also the measure
     * that matches what the limit exists to bound (memory and parse work),
     * and it needs no mbstring.
     */
    public static function templateTooLong(int $length, int $max): self
    {
        return new self("Template length {$length} exceeds the configured maximum of {$max} bytes");
    }

    public static function tooManyTokens(int $count, int $max): self
    {
        return new self("Template contains {$count} mustache tokens, exceeding the configured maximum of {$max}");
    }
}
