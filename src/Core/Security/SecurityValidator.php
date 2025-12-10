<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Security;

use AichaDigital\MustacheResolver\Exceptions\ModelNotAllowedException;

/**
 * Validates security constraints for model and attribute access.
 */
final readonly class SecurityValidator
{
    /**
     * @param  array<string>  $allowedModels
     * @param  array<string>  $blacklistedAttributes
     */
    public function __construct(
        private array $allowedModels = [],
        private array $blacklistedAttributes = [],
        private int $maxDepth = 10,
    ) {}

    /**
     * Check if a model class is allowed.
     *
     * @throws ModelNotAllowedException
     */
    public function validateModel(string $modelClass): void
    {
        if (empty($this->allowedModels)) {
            return; // All models allowed when list is empty
        }

        // Check full class name
        if (in_array($modelClass, $this->allowedModels, true)) {
            return;
        }

        // Check short class name
        $shortName = class_basename($modelClass);
        if (in_array($shortName, $this->allowedModels, true)) {
            return;
        }

        throw new ModelNotAllowedException($modelClass, $this->allowedModels);
    }

    /**
     * Check if an attribute is blacklisted.
     */
    public function isAttributeBlacklisted(string $attribute): bool
    {
        return in_array($attribute, $this->blacklistedAttributes, true);
    }

    /**
     * Check if depth exceeds maximum.
     */
    public function isDepthExceeded(int $depth): bool
    {
        return $depth > $this->maxDepth;
    }

    /**
     * Get allowed models list.
     *
     * @return array<string>
     */
    public function getAllowedModels(): array
    {
        return $this->allowedModels;
    }
}
