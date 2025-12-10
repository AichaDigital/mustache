<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Exceptions;

/**
 * Thrown when attempting to access a model that is not in the allowed list.
 */
class ModelNotAllowedException extends MustacheException
{
    public function __construct(
        private readonly string $modelClass,
        private readonly array $allowedModels,
    ) {
        parent::__construct(
            sprintf(
                'Model "%s" is not in the allowed models list. Allowed: %s',
                $modelClass,
                empty($allowedModels) ? '(all)' : implode(', ', $allowedModels)
            )
        );
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * @return array<string>
     */
    public function getAllowedModels(): array
    {
        return $this->allowedModels;
    }
}
