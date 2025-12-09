<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Accessors;

use AichaDigital\MustacheResolver\Contracts\DataAccessorInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Data accessor for Eloquent models.
 *
 * Supports:
 * - Direct attribute access
 * - Relation navigation
 * - Nested dot-notation paths
 */
final readonly class EloquentAccessor implements DataAccessorInterface
{
    public function __construct(
        private Model $model,
    ) {}

    public function get(string $path): mixed
    {
        return data_get($this->model, $path);
    }

    public function has(string $path): bool
    {
        $value = $this->get($path);

        return $value !== null;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        $keys = array_keys($this->model->getAttributes());

        // Add loaded relations
        foreach ($this->model->getRelations() as $relation => $value) {
            $keys[] = $relation;
        }

        return $keys;
    }

    public function getSourceType(): string
    {
        return 'eloquent';
    }

    public function getRaw(): Model
    {
        return $this->model;
    }

    /**
     * Get the model class name.
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model's short class name (without namespace).
     */
    public function getModelName(): string
    {
        return class_basename($this->model);
    }
}
