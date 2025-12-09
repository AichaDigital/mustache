<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter;

use AichaDigital\MustacheResolver\Contracts\FormatterInterface;
use AichaDigital\MustacheResolver\Exceptions\FormatterException;

/**
 * Registry for value formatters.
 *
 * This is a closed registry - only pre-defined formatters are allowed.
 * Custom formatters cannot be registered for security reasons.
 */
final class FormatterRegistry
{
    /**
     * Registered formatters indexed by name.
     *
     * @var array<string, FormatterInterface>
     */
    private array $formatters = [];

    /**
     * List of allowed formatter names (closed list).
     *
     * @var array<string>
     */
    private const ALLOWED_FORMATTERS = [
        // Date/Time formatters
        'toTimeString',
        'toDateString',
        'toDateTime',
        'toUnixTime',
        'toIso8601',
        'formatDate',
        // Numeric formatters
        'toInt',
        'toFloat',
        'toCents',
        'fromCents',
        'round',
        'floor',
        'ceil',
        'number',
        'percent',
        'abs',
        // String formatters
        'uppercase',
        'lowercase',
        'trim',
        'substr',
        'replace',
        'concat',
        'slug',
        'camel',
        'snake',
        'title',
    ];

    /**
     * Register a formatter.
     *
     * @throws FormatterException If formatter name is not in the allowed list
     */
    public function register(FormatterInterface $formatter): self
    {
        $name = $formatter->getName();

        if (! $this->isAllowed($name)) {
            throw FormatterException::notAllowed($name, self::ALLOWED_FORMATTERS);
        }

        $this->formatters[$name] = $formatter;

        return $this;
    }

    /**
     * Check if a formatter name is in the allowed list.
     */
    public function isAllowed(string $name): bool
    {
        return in_array($name, self::ALLOWED_FORMATTERS, true);
    }

    /**
     * Check if a formatter is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * Get a formatter by name.
     *
     * @throws FormatterException If formatter is not registered
     */
    public function get(string $name): FormatterInterface
    {
        if (! $this->has($name)) {
            if (! $this->isAllowed($name)) {
                throw FormatterException::notAllowed($name, self::ALLOWED_FORMATTERS);
            }

            throw FormatterException::notRegistered($name);
        }

        return $this->formatters[$name];
    }

    /**
     * Apply a formatter to a value.
     *
     * @param  array<int, mixed>  $arguments
     *
     * @throws FormatterException If formatting fails
     */
    public function apply(string $name, mixed $value, array $arguments = []): mixed
    {
        $formatter = $this->get($name);

        if (! $formatter->supports($value)) {
            throw FormatterException::unsupportedType($name, get_debug_type($value));
        }

        try {
            return $formatter->format($value, $arguments);
        } catch (FormatterException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw FormatterException::formattingFailed($name, $e->getMessage());
        }
    }

    /**
     * Get all registered formatter names.
     *
     * @return array<string>
     */
    public function getRegisteredNames(): array
    {
        return array_keys($this->formatters);
    }

    /**
     * Get all allowed formatter names.
     *
     * @return array<string>
     */
    public function getAllowedNames(): array
    {
        return self::ALLOWED_FORMATTERS;
    }

    /**
     * Get the count of registered formatters.
     */
    public function count(): int
    {
        return count($this->formatters);
    }

    /**
     * Create a registry with all built-in formatters registered.
     */
    public static function withBuiltins(): self
    {
        $registry = new self;

        // Date/Time formatters
        $registry->register(new Formatters\ToTimeStringFormatter);
        $registry->register(new Formatters\ToDateStringFormatter);
        $registry->register(new Formatters\ToDateTimeFormatter);
        $registry->register(new Formatters\ToUnixTimeFormatter);
        $registry->register(new Formatters\ToIso8601Formatter);
        $registry->register(new Formatters\FormatDateFormatter);

        // Numeric formatters
        $registry->register(new Formatters\ToIntFormatter);
        $registry->register(new Formatters\ToFloatFormatter);
        $registry->register(new Formatters\ToCentsFormatter);
        $registry->register(new Formatters\FromCentsFormatter);
        $registry->register(new Formatters\RoundFormatter);
        $registry->register(new Formatters\FloorFormatter);
        $registry->register(new Formatters\CeilFormatter);
        $registry->register(new Formatters\NumberFormatter);
        $registry->register(new Formatters\PercentFormatter);
        $registry->register(new Formatters\AbsFormatter);

        // String formatters
        $registry->register(new Formatters\UppercaseFormatter);
        $registry->register(new Formatters\LowercaseFormatter);
        $registry->register(new Formatters\TrimFormatter);
        $registry->register(new Formatters\SubstrFormatter);
        $registry->register(new Formatters\ReplaceFormatter);
        $registry->register(new Formatters\ConcatFormatter);
        $registry->register(new Formatters\SlugFormatter);
        $registry->register(new Formatters\CamelFormatter);
        $registry->register(new Formatters\SnakeFormatter);
        $registry->register(new Formatters\TitleFormatter);

        return $registry;
    }
}
