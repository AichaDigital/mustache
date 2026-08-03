<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Contracts\SecurityAwareAccessorInterface;

/**
 * Barrier 1 for a data accessor the resolver did not build itself.
 *
 * MustacheResolver::createContext() constructs the accessor for a Model, an
 * array or a plain object, and hands it the resolver's own validator. An
 * accessor supplied by the consumer arrives already built, so that injection
 * point does not exist — and for two token types barrier 2 cannot stand in:
 * NULL_COALESCE has hasSecurityPath() false (the sanitizer's path check never
 * runs) and DYNAMIC resolves its field name at runtime, so no static path
 * exists to re-check. For those, the accessor is the ONLY barrier. This
 * decorator restores it without requiring anything of the wrapped accessor.
 *
 * It applies the RESOLVER's validator, never merely trusting the wrapped
 * accessor's own policy: implementing SecurityAwareAccessorInterface does not
 * prove a policy exists — all three built-in accessors implement it and allow
 * everything when they were constructed with a null validator. Where the
 * wrapped accessor does carry its own policy, both must agree: a path is
 * allowed only if neither rejects it, so wrapping can never weaken it.
 *
 * getRaw() is DELIBERATELY plain delegation, preserving type and identity.
 * CollectionResolver navigates the raw source directly, and what protects
 * that route is its allowsPath() call BEFORE getRaw() — filtering or
 * substituting the raw value here would instead break the accessor contract
 * (EloquentAccessor::getRaw(): Model) and turn this class into a second,
 * divergent implementation of the sanitizer's container rules.
 */
final readonly class ValidatingAccessor implements DataAccessorInterface, SecurityAwareAccessorInterface
{
    public function __construct(
        private DataAccessorInterface $accessor,
        private SecurityValidator $validator,
    ) {
        // The root-datum class whitelist is applied here for the same reason
        // the rest of this class exists: EloquentAccessor validates the model
        // in ITS OWN constructor, so an accessor built by the consumer without
        // a validator never validated it at all.
        $this->validator->validateRootDatum($accessor->getRaw());
    }

    /**
     * Wrap an accessor unless doing so would be a no-op.
     *
     * In `off` mode every check short-circuits to "allowed", so wrapping
     * would change nothing except the accessor's identity and concrete type —
     * and `off` promises no checks are applied, which includes not
     * substituting what the consumer handed in.
     */
    public static function wrap(DataAccessorInterface $accessor, SecurityValidator $validator): DataAccessorInterface
    {
        if ($validator->getMode() === SecurityValidator::MODE_OFF) {
            return $accessor;
        }

        if ($accessor instanceof self && $accessor->validator === $validator) {
            return $accessor;
        }

        return new self($accessor, $validator);
    }

    public function get(string $path): mixed
    {
        return $this->allowsPath($path) ? $this->accessor->get($path) : null;
    }

    public function has(string $path): bool
    {
        return $this->allowsPath($path) && $this->accessor->has($path);
    }

    /**
     * Both policies must allow the path: the resolver's, and — when the
     * wrapped accessor carries one — its own.
     */
    public function allowsPath(string $path): bool
    {
        if (! $this->validator->allowsPath($path)) {
            return false;
        }

        return ! ($this->accessor instanceof SecurityAwareAccessorInterface)
            || $this->accessor->allowsPath($path);
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return $this->accessor->keys();
    }

    public function getSourceType(): string
    {
        return $this->accessor->getSourceType();
    }

    public function getRaw(): mixed
    {
        return $this->accessor->getRaw();
    }
}
