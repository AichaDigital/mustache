<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

/**
 * Accessor that can validate dot-notation paths against the security policy.
 *
 * Resolvers that navigate data without going through the accessor's get()
 * (e.g. collection traversal over the raw source) must consult this
 * interface so the blacklist and max_depth controls cannot be bypassed.
 */
interface SecurityAwareAccessorInterface
{
    /**
     * Whether the given dot-notation path may be resolved.
     */
    public function allowsPath(string $path): bool;
}
