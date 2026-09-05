<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'CH-'.$this->faker->unique()->numberBetween(100, 999),
            'name' => 'Chantier '.$this->faker->city(),
            'client_name' => $this->faker->company(),
            'is_active' => true,
        ];
    }
}
