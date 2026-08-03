<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;

/**
 * Barrier 1 for a context the resolver did not build itself.
 *
 * Decorating only the accessor would be insufficient: get() and has() are
 * access points of their own. ResolutionContext happens to delegate both to
 * its accessor (after checking its local variables), but ContextInterface
 * does not require that — a consumer's implementation may reach its data
 * without ever exposing it through getAccessor(). Both routes are therefore
 * gated here, and getAccessor() returns the decorated accessor so every
 * resolver that navigates through it is covered too.
 *
 * with() returns a DECORATED instance: it is the immutable-copy method used
 * during compound resolution, and returning the bare inner context there
 * would let a single with() call undo the whole barrier for the rest of the
 * resolution.
 */
final readonly class ValidatingContext implements ContextInterface
{
    private DataAccessorInterface $accessor;

    public function __construct(
        private ContextInterface $context,
        private SecurityValidator $validator,
    ) {
        $this->accessor = ValidatingAccessor::wrap($context->getAccessor(), $validator);
    }

    /**
     * Wrap a context unless doing so would be a no-op — see
     * ValidatingAccessor::wrap() for why `off` is returned untouched.
     */
    public static function wrap(ContextInterface $context, SecurityValidator $validator): ContextInterface
    {
        if ($validator->getMode() === SecurityValidator::MODE_OFF) {
            return $context;
        }

        if ($context instanceof self && $context->validator === $validator) {
            return $context;
        }

        return new self($context, $validator);
    }

    public function get(string $key): mixed
    {
        return $this->validator->allowsPath($key) ? $this->context->get($key) : null;
    }

    public function has(string $key): bool
    {
        return $this->validator->allowsPath($key) && $this->context->has($key);
    }

    public function with(string $key, mixed $value): static
    {
        return new self($this->context->with($key, $value), $this->validator);
    }

    public function getAccessor(): DataAccessorInterface
    {
        return $this->accessor;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->context->getVariables();
    }

    public function isStrict(): bool
    {
        return $this->context->isStrict();
    }

    public function getExpectedPrefix(): ?string
    {
        return $this->context->getExpectedPrefix();
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->context->config($key, $default);
    }
}
