<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Formatter\Formatters;

use AichaDigital\MustacheResolver\Core\Formatter\AbstractFormatter;

/**
 * Converts string to URL-friendly slug.
 *
 * Usage: {{expression|slug}}
 * Example: "Hello World!" → "hello-world"
 */
final class SlugFormatter extends AbstractFormatter
{
    protected array $supportedTypes = ['string', 'int', 'float'];

    public function getName(): string
    {
        return 'slug';
    }

    public function format(mixed $value, array $arguments = []): string
    {
        $separator = (string) $this->getArgument($arguments, 0, '-');
        $string = $this->toString($value);

        // Transliterate non-ASCII characters
        $string = $this->transliterate($string);

        // Convert to lowercase
        $string = mb_strtolower($string);

        // Replace non-alphanumeric characters with separator
        $string = preg_replace('/[^a-z0-9]+/', $separator, $string) ?? $string;

        // Remove leading/trailing separators
        return trim($string, $separator);
    }

    private function transliterate(string $string): string
    {
        $map = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ç' => 'C', 'ç' => 'c',
            'ß' => 'ss',
            'Ø' => 'O', 'ø' => 'o',
            'Æ' => 'AE', 'æ' => 'ae',
        ];

        return strtr($string, $map);
    }
}
