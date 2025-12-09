<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Exception thrown when configuration is invalid.
 */
class ConfigurationException extends MustacheException
{
    public static function missingResolver(string $name): self
    {
        return new self("Resolver not found: {$name}");
    }

    public static function invalidOption(string $key, mixed $value): self
    {
        $type = gettype($value);

        return new self("Invalid configuration value for '{$key}': got {$type}");
    }

    public static function missingRequired(string $key): self
    {
        return new self("Required configuration key missing: {$key}");
    }
}
