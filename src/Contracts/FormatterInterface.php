<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

/**
 * Interface for value formatters.
 *
 * Formatters transform resolved values into specific formats.
 * They are applied using the pipe syntax: {{expression|formatter}}
 */
interface FormatterInterface
{
    /**
     * Get the formatter name as it appears in templates.
     */
    public function getName(): string;

    /**
     * Format the given value.
     *
     * @param  mixed  $value  The value to format
     * @param  array<int, mixed>  $arguments  Optional arguments passed to formatter
     * @return mixed The formatted value
     */
    public function format(mixed $value, array $arguments = []): mixed;

    /**
     * Check if this formatter can handle the given value type.
     */
    public function supports(mixed $value): bool;
}
