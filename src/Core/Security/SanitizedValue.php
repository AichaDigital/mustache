<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

/**
 * A resolved value after security policy has been applied.
 *
 * Carries both representations because they diverge: a filtered model is an
 * array for the caller inspecting resolved values, but escaped JSON in the
 * rendered template. Computing both here keeps the decision in one place.
 */
final readonly class SanitizedValue
{
    public function __construct(
        public mixed $value,
        public string $text,
        public bool $blocked = false,
    ) {}

    /**
     * A value the policy refused: nothing recorded, nothing rendered.
     */
    public static function blocked(): self
    {
        return new self(null, '', true);
    }
}
