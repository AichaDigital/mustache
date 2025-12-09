<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use ArrayAccess;
use Countable;

/**
 * Resolves collection access patterns.
 *
 * Examples:
 *   {{User.posts.0.title}} → First post's title
 *   {{User.posts.first.title}} → First post's title (alias)
 *   {{User.posts.last.title}} → Last post's title
 *   {{User.posts.*.title}} → Array of all post titles
 *   {{User.posts.2.comments.0.body}} → Nested collection access
 */
final class CollectionResolver extends AbstractResolver
{
    private const KEYWORD_FIRST = 'first';

    private const KEYWORD_LAST = 'last';

    private const WILDCARD = '*';

    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::COLLECTION];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        if (! $this->prefixMatches($token, $context)) {
            return null;
        }

        $fieldPath = $token->getFieldPath();
        $current = $context->getAccessor()->getRaw();

        foreach ($fieldPath as $index => $segment) {
            if ($current === null) {
                return null;
            }

            // Handle wildcard - collect all values for remaining path
            if ($segment === self::WILDCARD) {
                $remainingPath = array_slice($fieldPath, $index + 1);

                return $this->resolveWildcard($current, $remainingPath);
            }

            // Handle first/last keywords
            if ($segment === self::KEYWORD_FIRST) {
                $current = $this->getFirst($current);

                continue;
            }

            if ($segment === self::KEYWORD_LAST) {
                $current = $this->getLast($current);

                continue;
            }

            // Handle numeric index
            if (is_numeric($segment)) {
                $current = $this->getByIndex($current, (int) $segment);

                continue;
            }

            // Handle regular property/key access
            $current = $this->getProperty($current, $segment);
        }

        return $current;
    }

    /**
     * Resolve wildcard pattern, collecting values from all items.
     *
     * @param  array<int, string>  $remainingPath
     * @return array<int, mixed>
     */
    private function resolveWildcard(mixed $collection, array $remainingPath): array
    {
        if (! is_iterable($collection)) {
            return [];
        }

        $results = [];

        foreach ($collection as $item) {
            $value = $item;

            foreach ($remainingPath as $segment) {
                if ($value === null) {
                    break;
                }

                $value = $this->getProperty($value, $segment);
            }

            if ($value !== null) {
                $results[] = $value;
            }
        }

        return $results;
    }

    /**
     * Get the first item from a collection.
     */
    private function getFirst(mixed $collection): mixed
    {
        if (is_array($collection)) {
            return $collection[array_key_first($collection)] ?? null;
        }

        if ($collection instanceof \Illuminate\Support\Collection) {
            return $collection->first();
        }

        if (is_iterable($collection)) {
            foreach ($collection as $item) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Get the last item from a collection.
     */
    private function getLast(mixed $collection): mixed
    {
        if (is_array($collection)) {
            return $collection[array_key_last($collection)] ?? null;
        }

        if ($collection instanceof \Illuminate\Support\Collection) {
            return $collection->last();
        }

        if ($collection instanceof Countable && $collection instanceof ArrayAccess) {
            $count = count($collection);

            return $count > 0 ? $collection[$count - 1] : null;
        }

        if (is_iterable($collection)) {
            $last = null;
            foreach ($collection as $item) {
                $last = $item;
            }

            return $last;
        }

        return null;
    }

    /**
     * Get item by numeric index.
     */
    private function getByIndex(mixed $collection, int $index): mixed
    {
        if (is_array($collection)) {
            return $collection[$index] ?? null;
        }

        if ($collection instanceof ArrayAccess) {
            return $collection[$index] ?? null;
        }

        if ($collection instanceof \Illuminate\Support\Collection) {
            return $collection->get($index);
        }

        return null;
    }

    /**
     * Get property from array or object.
     */
    private function getProperty(mixed $data, string $key): mixed
    {
        if (is_array($data)) {
            return $data[$key] ?? null;
        }

        if ($data instanceof ArrayAccess && isset($data[$key])) {
            return $data[$key];
        }

        if (is_object($data)) {
            // Try property access
            if (property_exists($data, $key)) {
                return $data->{$key};
            }

            // Try getter method
            $getter = 'get'.ucfirst($key);
            if (method_exists($data, $getter)) {
                return $data->{$getter}();
            }

            // Try direct access (for magic __get)
            return $data->{$key} ?? null;
        }

        return null;
    }

    public function priority(): int
    {
        return 40;
    }

    public function name(): string
    {
        return 'collection';
    }
}
