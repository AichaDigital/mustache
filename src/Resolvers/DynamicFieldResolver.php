<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Resolvers;

use AichaDigital\MustacheResolver\Contracts\ContextInterface;
use AichaDigital\MustacheResolver\Contracts\TokenInterface;
use AichaDigital\MustacheResolver\Core\Token\TokenType;
use AichaDigital\MustacheResolver\Exceptions\ResolutionException;

/**
 * Resolves dynamic field patterns where field name comes from another field.
 *
 * Examples:
 *   {{Device.$manufacturer.field_parameter}}
 *   1. Reads manufacturer.field_parameter → "imei"
 *   2. Uses "imei" as field name → Device.imei
 *   3. Returns the value of Device.imei
 *
 *   {{User.$config.preferred_field}}
 *   1. Reads config.preferred_field → "email"
 *   2. Returns User.email value
 */
final class DynamicFieldResolver extends AbstractResolver
{
    /**
     * @return TokenType[]
     */
    protected function supportedTypes(): array
    {
        return [TokenType::DYNAMIC];
    }

    public function resolve(TokenInterface $token, ContextInterface $context): mixed
    {
        $path = $token->getFieldPath();

        // Find the dynamic segment (starts with $)
        $dynamicIndex = $this->findDynamicIndex($path);

        if ($dynamicIndex === null) {
            return null;
        }

        // Build the path to resolve the field name indicator
        $dynamicSegment = substr($path[$dynamicIndex], 1); // Remove leading $
        $indicatorPath = array_slice($path, $dynamicIndex);
        $indicatorPath[0] = $dynamicSegment;

        // Resolve the field indicator to get the actual field name
        $fieldName = $this->navigatePath($indicatorPath, $context);

        if ($fieldName === null) {
            return null;
        }

        if (! is_string($fieldName)) {
            throw ResolutionException::forToken(
                $token,
                sprintf('Dynamic field indicator must resolve to string, got: %s', gettype($fieldName))
            );
        }

        // Now resolve the actual field on the base data
        return $context->getAccessor()->get($fieldName);
    }

    /**
     * Find the index of the first dynamic segment in the path.
     *
     * @param  array<int, string>  $path
     */
    private function findDynamicIndex(array $path): ?int
    {
        foreach ($path as $index => $segment) {
            if (str_starts_with($segment, '$')) {
                return $index;
            }
        }

        return null;
    }

    public function priority(): int
    {
        return 50;
    }

    public function name(): string
    {
        return 'dynamic';
    }
}
