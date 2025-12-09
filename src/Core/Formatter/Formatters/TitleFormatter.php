<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts string to Title Case.
 *
 * Usage: {{expression|title}}
 * Example: "hello world" → "Hello World"
 */
final class TitleFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'title';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $string = $this->toString($value);

        return mb_convert_case($string, MB_CASE_TITLE);
    }
}
