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
        private readonly mixed $inputValue,
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
