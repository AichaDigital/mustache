<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Department;
use Workbench\App\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'department_id' => null,
        ];
    }

    public function withDepartment(?Department $department = null): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department?->id ?? DepartmentFactory::new()->create()->id,
        ]);
    }
}
