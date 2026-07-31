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
     *
     * An enum stays raw in ->value (see sanitize() and stripBlacklisted()),
     * but the rendered ->text still needs a string: a backed enum renders
     * its backing value, a pure one its case name — neither has __toString()
     * by default, so without this branch it would fall through to ''.
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

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
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

        if ($array === null) {
            $this->validator?->reportViolation(
                'mustache-resolver: container could not be converted safely, blocked',
                ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
            );

            return SanitizedValue::blocked();
        }

        $found = [];
        $baseDepth = count($token->getPath());
        $pruned = false;
        $filtered = $this->stripBlacklisted($array, $found, $baseDepth, $pruned);

        if ($found !== []) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: blacklisted attributes stripped from serialized container'
                    : 'mustache-resolver: container contains blacklisted attribute(s), they would be filtered in enforce mode',
                ['path' => $token->getRaw(), 'blacklisted_attributes' => array_values(array_unique($found))],
            );
        }

        if ($pruned) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: serialized content pruned at max_depth'
                    : 'mustache-resolver: serialized content would be pruned at max_depth in enforce mode',
                ['path' => $token->getRaw(), 'token_depth' => $baseDepth, 'max_depth' => $this->validator->getMaxDepth()],
            );
        }

        if ($this->validator?->getMode() === SecurityValidator::MODE_REPORT) {
            return new SanitizedValue($raw, $this->renderContainer($this->stripBlacklisted($array, $found, $baseDepth, $pruned), $raw));
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
     * classifies as a container falls back to jsonSerialize(), iteration, or
     * finally its public properties.
     *
     * Wrapped in a try/catch on purpose: a conversion method that throws is
     * a container we cannot safely inspect, not an empty one — the caller
     * treats a null return as "block this", never as "nothing was here."
     * Likewise a conversion that returns something other than an array
     * (e.g. Arrayable::toArray() returning another object) is reported as
     * null rather than trusted, per §11.5.
     *
     * @return array<mixed>|null null when the value cannot be converted safely
     */
    private function toArray(mixed $value): ?array
    {
        try {
            if (is_array($value)) {
                return $value;
            }

            if ($value instanceof Arrayable) {
                $converted = $value->toArray();

                // Arrayable::toArray() declares no PHP return type, only a
                // PHPDoc one — a class can implement the interface and still
                // return something else at runtime without a TypeError.
                // PHPStan trusts the PHPDoc and calls this branch dead; it is
                // exactly the failure mode §11.5 requires treated as blocked,
                // not silently accepted.
                return is_array($converted) ? $converted : null; // @phpstan-ignore function.alreadyNarrowedType
            }

            if ($value instanceof \JsonSerializable) {
                $converted = $value->jsonSerialize();

                return is_array($converted) ? $converted : null;
            }

            if ($value instanceof \Traversable) {
                return iterator_to_array($value);
            }

            return is_object($value) ? get_object_vars($value) : [];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Remove blacklisted keys recursively, prune past max_depth, cut cycles.
     *
     * Recursion decisions go through isContainer() — the same classification
     * used for the root value — so a model nested inside an authorised array
     * cannot escape filtering by being Stringable (see the class docblock on
     * isContainer() for why the precedence order matters). A nested object
     * that isContainer() classifies as atomic is normalised the same way the
     * root value is in sanitize(): an enum passes through raw, anything else
     * is rendered to its string form. Leaving it as a raw object would let
     * it dodge json_encode() (a Stringable-only value serialises as `{}`)
     * while the recorded array/value would still carry the live object.
     *
     * Depth counts from the context root, not from this call's own
     * recursion: $depth arrives seeded with the token's path depth (see
     * sanitiseContainer()), so a shallow token serialising a deep structure
     * is limited the same way a deep token is. At each step, before
     * descending into a nested container, this checks the depth its
     * children would land at — $depth + 2: one level for the container
     * itself (the key currently being visited), one more for what is
     * inside it — against max_depth. Exceeding it prunes that branch,
     * replacing it with an empty array, rather than discarding the whole
     * container the way Barrier 2's earlier gate does.
     *
     * Cycles: $seen tracks objects already expanded in this traversal. An
     * object is attached right before it is expanded into an array and
     * recursed into; if it is encountered again (the structure loops back
     * to it), it is cut to null and reported as pruned instead of being
     * expanded a second time — that second expansion is what would recurse
     * forever. $seen defaults fresh per top-level call, which is correct:
     * two independent traversals of the same data (e.g. the extra report-
     * mode pass in sanitiseContainer()) must not treat one call's visited
     * set as carrying over into the other's.
     *
     * A nested toArray() failure (see toArray()'s own contract) degrades to
     * an empty array rather than blocking, unlike the root-level failure
     * guarded in sanitiseContainer(): there is no SanitizedValue::blocked()
     * to return for a single key partway through a larger structure, so the
     * offending branch is simply dropped.
     *
     * @param  array<mixed>  $data
     * @param  array<int, string>  $found
     * @param  \SplObjectStorage<object, mixed>|null  $seen
     * @return array<mixed>
     */
    private function stripBlacklisted(
        array $data,
        array &$found = [],
        int $depth = 0,
        bool &$pruned = false,
        ?\SplObjectStorage $seen = null,
    ): array {
        $seen ??= new \SplObjectStorage;

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->validator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
                unset($data[$key]);

                continue;
            }

            if (is_object($value)) {
                if (! $this->isContainer($value)) {
                    $data[$key] = $value instanceof \UnitEnum ? $value : $this->render($value);

                    continue;
                }

                if ($seen->contains($value)) {
                    $data[$key] = null;
                    $pruned = true;

                    continue;
                }

                $seen->attach($value);
                $value = $this->toArray($value) ?? [];
            }

            if (is_array($value)) {
                if ($this->validator?->isDepthExceeded($depth + 2) === true) {
                    $data[$key] = [];
                    $pruned = true;

                    continue;
                }

                $data[$key] = $this->stripBlacklisted($value, $found, $depth + 1, $pruned, $seen);
            }
        }

        return $data;
    }
}
