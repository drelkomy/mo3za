<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'status' => 'active',
            'price_paid' => $this->faker->randomFloat(2, 10, 200),
            'tasks_created' => 0,
            'max_tasks' => 10,
            'max_milestones_per_task' => 5,
            'start_date' => now(),
            'end_date' => null,
        ];
    }
}
