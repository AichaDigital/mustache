<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Thrown when a formatter fails to process a value.
 */
final class FormatterException extends ResolutionException
{
    public function __construct(
        private readonly string $formatterName,
        private readonly mixed $inputValue = null,
        private readonly ?string $reason = null,
    ) {
        $message = sprintf(
            'Formatter "%s" failed to process value %s',
            $formatterName,
            json_encode($inputValue),
        );

        if ($reason !== null) {
            $message .= sprintf(': %s', $reason);
        }

        parent::__construct($message);
    }

    /**
     * Create exception for a formatter not in the allowed list.
     *
     * @param  array<string>  $allowedNames
     */
    public static function notAllowed(string $name, array $allowedNames): self
    {
        $instance = new self(
            $name,
            null,
            sprintf(
                'Formatter is not in the allowed list. Allowed formatters: %s',
                implode(', ', $allowedNames),
            ),
        );

        return $instance;
    }

    /**
     * Create exception for a formatter not registered.
     */
    public static function notRegistered(string $name): self
    {
        return new self(
            $name,
            null,
            'Formatter is not registered. Use FormatterRegistry::register() first.',
        );
    }

    /**
     * Create exception for unsupported value type.
     */
    public static function unsupportedType(string $name, string $type): self
    {
        return new self(
            $name,
            null,
            sprintf('Formatter does not support values of type "%s"', $type),
        );
    }

    /**
     * Create exception for formatting failure.
     */
    public static function formattingFailed(string $name, string $errorMessage): self
    {
        return new self($name, null, $errorMessage);
    }

    /**
     * Get the formatter name that failed.
     */
    public function getFormatterName(): string
    {
        return $this->formatterName;
    }

    /**
     * Get the input value that caused the failure.
     */
    public function getInputValue(): mixed
    {
        return $this->inputValue;
    }

    /**
     * Get the reason for the failure (if available).
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
