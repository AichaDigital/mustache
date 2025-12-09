<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter;

use AichaDigital\MustacheResolver\Contracts\FormatterInterface;

/**
 * Base class for formatters with common functionality.
 */
abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * The types this formatter supports.
     *
     * @var array<string>
     */
    protected array $supportedTypes = [];

    public function supports(mixed $value): bool
    {
        if (empty($this->supportedTypes)) {
            return true;
        }

        $type = get_debug_type($value);

        foreach ($this->supportedTypes as $supportedType) {
            if ($type === $supportedType) {
                return true;
            }

            // Handle parent class/interface matching
            if (is_object($value) && is_a($value, $supportedType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get an argument with a default value.
     *
     * @param  array<int, mixed>  $arguments
     */
    protected function getArgument(array $arguments, int $index, mixed $default = null): mixed
    {
        return $arguments[$index] ?? $default;
    }

    /**
     * Convert value to string safely.
     */
    protected function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return json_encode($value) ?: '';
    }

    /**
     * Convert value to numeric safely.
     */
    protected function toNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return 0;
    }
}
