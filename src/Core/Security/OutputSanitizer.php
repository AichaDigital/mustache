<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Contracts\SafeForTemplateSerialization;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

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
        if ($this->validator === null || $this->validator->getMode() === SecurityValidator::MODE_OFF) {
            return new SanitizedValue($raw, $this->render($raw));
        }

        if ($this->isContainer($raw) && ! $this->maySerialiseWhole($raw)) {
            $this->validator->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: container blocked by security policy'
                    : 'mustache-resolver: container would be blocked in enforce mode',
                ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
            );

            if ($this->validator->getMode() === SecurityValidator::MODE_ENFORCE) {
                return SanitizedValue::blocked();
            }
        }

        // An object treated as atomic must not survive raw into resolvedValues:
        // TranslationResult::toArray() would keep the object, and a later
        // serialization could expose the structure this barrier just decided
        // not to expose. Text and recorded value become the same string.
        if (is_object($raw) && ! $raw instanceof \UnitEnum && ! $this->isContainer($raw)) {
            $text = $this->render($raw);

            return new SanitizedValue($text, $text);
        }

        return new SanitizedValue($raw, $this->render($raw));
    }

    /**
     * Structural classification, in strict precedence order.
     *
     * Order matters and is the whole point. PHP 8 makes every class with
     * __toString() implicitly Stringable, so Eloquent models and collections
     * are Stringable — testing that first would classify the two types this
     * barrier exists for as harmless scalars.
     *
     *   array                     → container
     *   non-object                → scalar
     *   UnitEnum                  → scalar
     *   Arrayable or Traversable  → container
     *   Stringable                → atomic renderable
     *   any remaining object      → container
     *
     * JsonSerializable is deliberately absent: it is a conversion mechanism,
     * not evidence of being a container. Carbon implements it and is an
     * atomic value.
     */
    private function isContainer(mixed $value): bool
    {
        if (is_array($value)) {
            return true;
        }

        if (! is_object($value)) {
            return false;
        }

        if ($value instanceof \UnitEnum) {
            return false;
        }

        if ($value instanceof Arrayable || $value instanceof \Traversable) {
            return true;
        }

        if ($value instanceof \Stringable) {
            return false;
        }

        return true;
    }

    /**
     * Whether whole serialisation is permitted for this value.
     *
     * The two escapes are not interchangeable: the global flag exists for
     * plain arrays and collections, which cannot implement an interface. A
     * model has a class, so it opts in through that class or not at all —
     * letting the flag authorise models would collapse the distinction.
     */
    private function maySerialiseWhole(mixed $value): bool
    {
        if ($value instanceof SafeForTemplateSerialization) {
            return true;
        }

        if (! $this->allowContainerSerialization) {
            return false;
        }

        return ! (class_exists(Model::class)
            && $value instanceof Model);
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
