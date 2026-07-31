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

        if ($this->isContainer($raw)) {
            return $this->sanitiseContainer($raw, $token);
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

    /**
     * Filter an authorised container and build both representations.
     *
     * "Authorised" here means the container reached this point without being
     * blocked above (whitelisted class, the global flag, or report mode) —
     * not that it is exempt from the blacklist. Stripping still applies.
     */
    private function sanitiseContainer(mixed $raw, TokenInterface $token): SanitizedValue
    {
        $array = $this->toArray($raw);
        $found = [];
        $filtered = $this->stripBlacklisted($array, $found);

        if ($found !== []) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: blacklisted attributes stripped from serialized container'
                    : 'mustache-resolver: container contains blacklisted attribute(s), they would be filtered in enforce mode',
                ['path' => $token->getRaw(), 'blacklisted_attributes' => array_values(array_unique($found))],
            );
        }

        if ($this->validator?->getMode() === SecurityValidator::MODE_REPORT) {
            return new SanitizedValue($raw, $this->renderContainer($this->stripBlacklisted($array), $raw));
        }

        return new SanitizedValue($filtered, $this->renderContainer($filtered, $raw));
    }

    /**
     * Render a container as JSON, preserving Eloquent's casting escape.
     *
     * This is what modelToString() did in 2.1 and it is contract: a consumer
     * serialising a whole relation gets JSON, not an imploded list. The escape
     * flag is protected, so it is read through a closure bound to the model.
     *
     * @param  array<mixed>  $filtered
     */
    private function renderContainer(array $filtered, mixed $original): string
    {
        $json = json_encode($filtered) ?: '';

        if (! is_object($original) || ! property_exists($original, 'escapeWhenCastingToString')) {
            return $json;
        }

        $reader = function (): bool {
            // @phpstan-ignore-next-line — bound to the model to read its protected flag
            return (bool) $this->escapeWhenCastingToString;
        };

        return $reader->call($original) ? e($json) : $json;
    }

    /**
     * Convert a container to its array form for filtering.
     *
     * Reuses isContainer()'s classification for the recursive case (see
     * stripBlacklisted()): anything that is not array or Arrayable but still
     * classifies as a container falls back to its public properties.
     *
     * @return array<mixed>
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return is_object($value) ? get_object_vars($value) : [];
    }

    /**
     * Remove blacklisted keys recursively, collecting what was removed.
     *
     * Recursion decisions go through isContainer() — the same classification
     * used for the root value — so a model nested inside an authorised array
     * cannot escape filtering by being Stringable (see the class docblock on
     * isContainer() for why the precedence order matters).
     *
     * @param  array<mixed>  $data
     * @param  array<int, string>  $found
     * @return array<mixed>
     */
    private function stripBlacklisted(array $data, array &$found = []): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->validator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
                unset($data[$key]);

                continue;
            }

            if ($this->isContainer($value)) {
                $data[$key] = $this->stripBlacklisted($this->toArray($value), $found);
            }
        }

        return $data;
    }
}
