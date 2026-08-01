<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Accessors;

use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use AichaDigital\MustacheResolver\Contracts\SecurityAwareAccessorInterface;
use AichaDigital\MustacheResolver\Core\Security\SecurityValidator;

/**
 * Data accessor for generic PHP objects.
 */
final readonly class ObjectAccessor implements DataAccessorInterface, SecurityAwareAccessorInterface
{
    public function __construct(
        private object $object,
        private ?SecurityValidator $securityValidator = null,
    ) {}

    public function get(string $path): mixed
    {
        if (! $this->allowsPath($path)) {
            return null;
        }

        $segments = explode('.', $path);
        $current = $this->object;

        foreach ($segments as $segment) {
            if ($current === null) {
                return null;
            }

            $current = $this->getProperty($current, $segment);
        }

        return $current;
    }

    /**
     * Check every segment against the blacklist and the path depth.
     */
    public function allowsPath(string $path): bool
    {
        return $this->securityValidator === null || $this->securityValidator->allowsPath($path);
    }

    public function has(string $path): bool
    {
        return $this->get($path) !== null;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        if (method_exists($this->object, 'toArray')) {
            /** @var array<int, string> $keys */
            $keys = array_keys($this->object->toArray());

            return $keys;
        }

        /** @var array<int, string> $keys */
        $keys = array_keys(get_object_vars($this->object));

        return $keys;
    }

    public function getSourceType(): string
    {
        return 'object';
    }

    public function getRaw(): object
    {
        return $this->object;
    }

    /**
     * Get a property from an object.
     */
    private function getProperty(mixed $object, string $key): mixed
    {
        if (! is_object($object)) {
            return null;
        }

        // Try array access
        if ($object instanceof \ArrayAccess && isset($object[$key])) {
            return $object[$key];
        }

        // Try direct property
        if (property_exists($object, $key)) {
            return $object->{$key};
        }

        // Try getter
        $getter = 'get'.ucfirst($key);
        if (method_exists($object, $getter)) {
            return $object->{$getter}();
        }

        // Try magic __get
        if (method_exists($object, '__get')) {
            return $object->{$key};
        }

        return null;
    }
}
