<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'address' => fake()->streetAddress(),
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'postal_code' => '400001',
            'country' => 'India',
            'status' => 'active',
            'account_manager_id' => User::factory(),
        ];
    }

    /** Client in another state (IGST applies for a Maharashtra-registered seller). */
    public function interState(): static
    {
        return $this->state(fn () => ['city' => 'Bengaluru', 'state' => 'Karnataka', 'state_code' => '29']);
    }
}
