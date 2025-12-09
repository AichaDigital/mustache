<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Thrown when a USE clause has invalid syntax.
 *
 * Valid syntax: USE {var} => {{mustache}} [condition] && statement
 */
final class InvalidUseSyntaxException extends ParseException
{
    public function __construct(
        private readonly string $template,
        private readonly ?string $hint = null,
    ) {
        $message = sprintf('Invalid USE clause syntax in template: %s', $this->truncate($template, 100));

        if ($hint !== null) {
            $message .= sprintf(' Hint: %s', $hint);
        }

        parent::__construct($message);
    }

    /**
     * Get the template that failed to parse.
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Get the hint about what's wrong (if available).
     */
    public function getHint(): ?string
    {
        return $this->hint;
    }

    /**
     * Truncate a string for display in error messages.
     */
    private function truncate(string $string, int $length): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length - 3).'...';
    }
}
