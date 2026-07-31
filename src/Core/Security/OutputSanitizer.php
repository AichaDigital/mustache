<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Contracts\SafeForTemplateSerialization;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * Barrier 2: every value resolved through MustacheResolver::translate()
 * passes through here before it forks into the rendered template and the
 * recorded resolved values.
 *
 * Scope, precisely: every resolver in the default pipeline reached via
 * translate()'s token loop is covered, because that loop calls sanitize()
 * on every result unconditionally — a resolver cannot bypass it from
 * inside that loop. The known exception is compound expressions:
 * Core\Compound\UseVariableResolver calls the pipeline directly and
 * substitutes the resolved value without ever reaching this class. That
 * path is inert today — no default resolver handles TokenType::COMPOUND
 * and CompoundResolver is not registered anywhere — so it is not a live
 * gap, but it will need its own wiring when compound expressions are
 * exposed (Phase 2), not an assumption that this barrier already covers it.
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

        // A resolver that navigates on its own can return a plain scalar,
        // clearing the accessor entirely; the string itself carries no mark
        // of origin. Re-checking the token's own path here covers that case
        // for tokens where a path is meaningful at all. getSecurityPath()
        // returns the SAME string the accessor itself received (per
        // resolver: field path with prefix stripped, or full path for
        // TABLE) — that is what makes this check land on the same dedup
        // key as barrier 1's, instead of manufacturing a second key for
        // the same logical path. It is null for tokens with no static path
        // (hasSecurityPath() false, or DYNAMIC, whose two accesses are
        // already validated independently by the accessor). allowsPath()
        // already reports through the validator's reporter and already
        // returns true in report mode, so no extra reporting or mode
        // branching belongs here; the accessor usually reports first
        // without knowing the full token, so it is this second call that
        // gets deduplicated away, not the other way round.
        $securityPath = $token->getSecurityPath();

        if ($securityPath !== null && ! $this->validator->allowsPath($securityPath)) {
            return SanitizedValue::blocked();
        }

        // Scalar projection escape, checked ONLY on the resolved root value
        // and ONLY after the path check above — ordering is what keeps
        // {{User.posts.*.email}} blocked by the blacklist on `email` while
        // {{User.posts.*.title}} resolves: the projection never gets a
        // chance to override a path the validator already rejected.
        //
        // CollectionResolver's wildcard produces a plain PHP list of
        // already-extracted field values (see resolveWildcard()), not a
        // raw structure — isContainer()'s blanket is_array() => true
        // would otherwise block it under the same "container" gate as an
        // actual whole-model dump. This escape recognises that specific
        // shape without weakening isContainer() itself: it never runs
        // inside the recursive container-filtering walk, so a nested
        // array under a blacklist-checked key is still filtered as a
        // container, list-shaped or not.
        //
        // Trade-off, deliberately not closed here: the sanitizer cannot
        // prove each scalar actually came from the declared path — it
        // only knows the shape of what it received. That guarantee rests
        // on the package's own resolvers; a consumer-registered resolver
        // is trusted code, the same limit already accepted for a resolver
        // that returns a bare scalar under an innocuous token.
        if ($this->isScalarProjection($raw)) {
            return $this->sanitizeScalarProjection($raw);
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

        // An atomic object (Carbon, any other Stringable) is only
        // normalised to a string in enforce mode — Phase 1's promise is
        // that installing this branch unchanged (default mode: report)
        // changes NOTHING for a consumer. Normalising it in report too
        // would silently swap a Carbon for a string in getResolvedValues()
        // before enforce is ever turned on, breaking that promise, and
        // report protects nothing by doing so: nothing is blocked in
        // report, so there is no leak this pre-empts.
        //
        // Both branches report — the change is worth surfacing either way.
        // In report, this is the ONLY signal a consumer gets that upgrading
        // to enforce will change a value's type, not just its presence; a
        // measuring tool that hides the type change defeats its purpose.
        // In enforce, the object survives nowhere (TranslationResult::
        // toArray() would otherwise keep it, and a later serialization
        // could expose structure this barrier just decided not to expose),
        // so the normalisation is reported as the policy action it is,
        // even though — unlike a block — the rendered text never changes.
        if (is_object($raw) && ! $raw instanceof \UnitEnum && ! $this->isContainer($raw)) {
            $text = $this->render($raw);

            if ($this->validator->getMode() === SecurityValidator::MODE_ENFORCE) {
                $this->validator->reportViolation(
                    'mustache-resolver: atomic object normalized to string by security policy',
                    ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
                );

                return new SanitizedValue($text, $text);
            }

            $this->validator->reportViolation(
                'mustache-resolver: atomic object would be normalized to string in enforce mode',
                ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
            );

            return new SanitizedValue($raw, $text);
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
     * Whether a value is a scalar projection: a list built by a resolver's
     * own field extraction (CollectionResolver's wildcard), not a raw
     * structure being handed to the barrier for a decision.
     *
     * Deliberately a SEPARATE, EARLIER classification from isContainer() —
     * this does not change what isContainer() considers a container, it
     * only recognises a specific shape before that gate is reached. Only
     * called on the resolved root value in sanitize(); never inside
     * stripBlacklisted()'s recursion, so a nested list under a
     * blacklist-checked key is still filtered as a container regardless
     * of its own shape.
     *
     * A list (array_is_list(): sequential, 0-based keys) qualifies when
     * every element is scalar, null, an enum, or an atomic-renderable
     * Stringable object (per isContainer()'s own precedence — an object
     * that is ALSO Arrayable/Traversable is still a container, checked
     * via isContainer() itself rather than duplicated here). An empty
     * list qualifies: it carries no information to leak.
     *
     * Disqualified, all falling through to the ordinary container gate:
     * an associative array — its keys ARE field names, so it is a dump,
     * not a projection; any element that is itself an array, a Model, a
     * Collection, Arrayable or Traversable — raw structure inside the
     * list; and anything else not classified as atomic (a resource, for
     * instance), which the container gate then blocks.
     */
    private function isScalarProjection(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $element) {
            if ($element === null || is_scalar($element) || $element instanceof \UnitEnum) {
                continue;
            }

            if ($element instanceof \Stringable && ! $this->isContainer($element)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Build the SanitizedValue for a list that passed isScalarProjection().
     *
     * value: every atomic-renderable element normalised to its rendered
     * string — ONLY in enforce mode, the same "report changes nothing"
     * rule sanitize() applies to a bare atomic object at the root (see
     * that branch's docblock). In report, elements keep their original
     * identity and type; enforce is the only mode that must never let a
     * raw object survive into getResolvedValues(). Enums are the one
     * exception even in enforce, kept raw, by the same convention used
     * everywhere else in this class (see the class docblock on
     * stripBlacklisted() and sanitize()'s object branch).
     *
     * text: the legacy projection rendering, identical in both modes.
     * render()'s array branch is the same implode(', ', ...) that
     * v2.1's valueToString() produced for arrays — a wildcard projection
     * renders exactly as it always did. Never renderContainer()'s JSON,
     * which is for authorised containers, not projections.
     *
     * @param  array<int, mixed>  $list
     */
    private function sanitizeScalarProjection(array $list): SanitizedValue
    {
        $text = $this->render($list);

        if ($this->validator?->getMode() !== SecurityValidator::MODE_ENFORCE) {
            return new SanitizedValue($list, $text);
        }

        $normalised = array_map(
            fn (mixed $element): mixed => (is_object($element) && ! $element instanceof \UnitEnum)
                ? $this->render($element)
                : $element,
            $list,
        );

        return new SanitizedValue($normalised, $text);
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
     * Segment count of a canonical security path, for seeding the
     * container walk's depth budget.
     *
     * Mirrors exactly how SecurityValidator::allowsPath() counts depth
     * (explode('.', $path), then count()) — see sanitiseContainer()'s use
     * of this for why matching that count is the whole point: the path
     * check and the container walk must measure the SAME token the SAME
     * way, or one blocks/prunes at a different depth than the other
     * allows, which is the false-block class Fix 1 (getSecurityPath())
     * already existed to remove for the path check itself.
     *
     * A null or empty path — no static path to measure (hasSecurityPath()
     * false, or DYNAMIC, whose real access is validated by the accessor
     * at runtime, never by a path here) — seeds depth 0. Nothing
     * validated this token's own position by depth in the first place,
     * so only the container's OWN nested structure should count against
     * max_depth, not an assumed position for the token itself.
     */
    private function pathDepth(?string $securityPath): int
    {
        return $securityPath === null || $securityPath === ''
            ? 0
            : count(explode('.', $securityPath));
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
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: container could not be converted safely, blocked'
                    : 'mustache-resolver: container could not be converted safely, would be blocked in enforce mode',
                ['path' => $token->getRaw(), 'type' => get_debug_type($raw)],
            );

            if ($this->validator?->getMode() === SecurityValidator::MODE_ENFORCE) {
                return SanitizedValue::blocked();
            }

            // Report observes without altering: the original object survives
            // with its own identity and the rendering it would have had with
            // security off — never a partially-converted array, and never
            // ->blocked(). Mirrors the container-blocked gate in sanitize().
            $text = $this->render($raw);

            return new SanitizedValue($raw, $text);
        }

        $found = [];
        $baseDepth = $this->pathDepth($token->getSecurityPath());
        $depthPruned = false;
        $cycleCut = false;
        $conversionFailed = false;

        // The root itself is an ancestor: without seeding it here, a cycle
        // that points back to the ROOT (A → B → A) is only recognised one
        // hop later than it should be — stripBlacklisted() only attaches
        // objects it encounters as nested values, so the root, converted
        // to $array above and never passed through that loop, would never
        // be in $seen on its own. Only objects seed the chain; a raw array
        // root has no identity to re-encounter.
        $seen = new \SplObjectStorage;

        if (is_object($raw)) {
            $seen->attach($raw);
        }

        try {
            $filtered = $this->stripBlacklisted($array, $found, $baseDepth, $depthPruned, $cycleCut, $conversionFailed, $seen);
        } finally {
            if (is_object($raw)) {
                $seen->detach($raw);
            }
        }

        if ($found !== []) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: blacklisted attributes stripped from serialized container'
                    : 'mustache-resolver: container contains blacklisted attribute(s), they would be filtered in enforce mode',
                ['path' => $token->getRaw(), 'blacklisted_attributes' => array_values(array_unique($found))],
            );
        }

        if ($depthPruned) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: serialized content pruned at max_depth'
                    : 'mustache-resolver: serialized content would be pruned at max_depth in enforce mode',
                ['path' => $token->getRaw(), 'token_depth' => $baseDepth, 'max_depth' => $this->validator->getMaxDepth()],
            );
        }

        if ($cycleCut) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: cyclic reference cut from serialized content'
                    : 'mustache-resolver: cyclic reference would be cut from serialized content in enforce mode',
                ['path' => $token->getRaw()],
            );
        }

        if ($conversionFailed) {
            $this->validator?->reportViolation(
                $this->validator->getMode() === SecurityValidator::MODE_ENFORCE
                    ? 'mustache-resolver: nested value could not be converted safely, dropped'
                    : 'mustache-resolver: nested value could not be converted safely, would be dropped in enforce mode',
                ['path' => $token->getRaw()],
            );
        }

        // Report observes without altering: the rendered text must stay
        // exactly what v2.1 produced (a model's own __toString(), a
        // collection's own toJson(), an array's imploded scalars — all of
        // which render() reproduces), or report mode stops being a safe
        // pre-upgrade measuring tool and starts changing template output
        // on its own.
        if ($this->validator?->getMode() === SecurityValidator::MODE_REPORT) {
            return new SanitizedValue($raw, $this->render($raw));
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
     * Cycles: $seen holds the CURRENT ANCESTOR CHAIN, not every object ever
     * seen. An object is attached right before its branch is entered
     * (converted and recursed into) and detached — via try/finally, so an
     * exception thrown anywhere in that branch still unwinds the stack —
     * once that branch is fully processed. Only a re-encounter of an object
     * still on that chain (A → … → A) is a cycle; the same object appearing
     * a second time as a sibling, or in an unrelated branch, is not — by the
     * time its second occurrence is visited, its first occurrence has
     * already been detached, so it serialises again normally. That is data
     * (e.g. the same related model under two attributes), not a cycle, and
     * dropping it would be a silent, misleading loss. $seen defaults fresh
     * per top-level call, which is correct: two independent traversals of
     * the same data (e.g. the extra report-mode pass in sanitiseContainer())
     * must not treat one call's ancestor chain as carrying over into the
     * other's.
     *
     * Three causes can alter a value here, and they are tracked in three
     * separate out-parameters so a caller's report can never attribute one
     * to another: $depthPruned (exceeding max_depth — replaces the branch
     * with []), $cycleCut (an ancestor re-encountered — replaces the value
     * with null), $conversionFailed (a nested toArray() returning null —
     * see toArray()'s own contract — degrades to an empty array). Only the
     * first two are visible in ->value's shape; the third looks identical
     * to an object that legitimately had no properties, which is why it is
     * still reported separately even though it is not otherwise observable.
     * Unlike the root-level failure guarded in sanitiseContainer(), there is
     * no SanitizedValue::blocked() to return for a single key partway
     * through a larger structure — the offending branch is simply dropped.
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
        bool &$depthPruned = false,
        bool &$cycleCut = false,
        bool &$conversionFailed = false,
        ?\SplObjectStorage $seen = null,
    ): array {
        $seen ??= new \SplObjectStorage;

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->validator?->isAttributeBlacklisted($key)) {
                $found[] = $key;
                unset($data[$key]);

                continue;
            }

            $ancestor = null;

            if (is_object($value)) {
                if (! $this->isContainer($value)) {
                    $data[$key] = $value instanceof \UnitEnum ? $value : $this->render($value);

                    continue;
                }

                if ($seen->contains($value)) {
                    $data[$key] = null;
                    $cycleCut = true;

                    continue;
                }

                $ancestor = $value;
                $seen->attach($ancestor);
            }

            try {
                if ($ancestor !== null) {
                    $value = $this->toArray($ancestor);

                    if ($value === null) {
                        $data[$key] = [];
                        $conversionFailed = true;

                        continue;
                    }
                }

                if (is_array($value)) {
                    if ($this->validator?->isDepthExceeded($depth + 2) === true) {
                        $data[$key] = [];
                        $depthPruned = true;

                        continue;
                    }

                    $data[$key] = $this->stripBlacklisted($value, $found, $depth + 1, $depthPruned, $cycleCut, $conversionFailed, $seen);
                }
            } finally {
                // Leaving this branch: whatever is still ahead in the loop
                // (siblings, or the caller's own siblings once this call
                // returns) must see $ancestor as available again, cycle or
                // not, success or exception.
                if ($ancestor !== null) {
                    $seen->detach($ancestor);
                }
            }
        }

        return $data;
    }
}
