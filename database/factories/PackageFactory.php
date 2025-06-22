<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'price' => $this->faker->randomFloat(2, 10, 200),
            'max_tasks' => $this->faker->numberBetween(5, 50),
            'max_milestones_per_task' => $this->faker->numberBetween(3, 10),
            'is_active' => true,
        ];
    }
}
