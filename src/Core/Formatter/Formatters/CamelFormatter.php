<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts string to camelCase.
 *
 * Usage: {{expression|camel}}
 * Example: "hello_world" → "helloWorld"
 */
final class CamelFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'camel';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $string = $this->toString($value);

        // Split by non-alphanumeric characters
        $words = preg_split('/[^a-zA-Z0-9]+/', $string, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || empty($words)) {
            return '';
        }

        $result = mb_strtolower($words[0]);

        for ($i = 1; $i < count($words); $i++) {
            $result .= ucfirst(mb_strtolower($words[$i]));
        }

        return $result;
    }
}
