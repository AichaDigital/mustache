<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Contracts\TokenInterface;

/**
 * Barrier 2: every resolved value passes through here before it forks into
 * the rendered template and the recorded resolved values.
 *
 * A resolver cannot bypass it, because it sits downstream of all of them.
 */
final readonly class OutputSanitizer
{
    public function __construct(
        private ?SecurityValidator $validator = null,
        private bool $allowContainerSerialization = false,
    ) {}

    public function sanitize(mixed $raw, TokenInterface $token): SanitizedValue
    {
        return new SanitizedValue($raw, $this->render($raw));
    }

    /**
     * Render a sanitised value for template substitution.
     */
    private function render(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v): string => $this->render($v), $value));
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : '';
        }

        return (string) $value;
    }
}
