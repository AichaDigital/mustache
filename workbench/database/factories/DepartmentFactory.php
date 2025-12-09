<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Department;
use Workbench\App\Models\User;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('DEPT-###'),
            'manager_id' => null,
        ];
    }

    public function withManager(?User $manager = null): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $manager?->id ?? UserFactory::new()->create()->id,
        ]);
    }
}
