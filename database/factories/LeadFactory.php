<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'lead_source_id' => LeadSource::inRandomOrder()->value('id'),
            'lead_status_id' => LeadStatus::inRandomOrder()->value('id') ?? LeadStatus::factory(),
            'assigned_to' => User::factory(),
            'created_by' => fn (array $attributes) => $attributes['assigned_to'],
            'value' => fake()->randomFloat(2, 100, 20000),
            'notes' => fake()->optional()->sentence(),
            'next_follow_up_at' => fake()->optional()->dateTimeBetween('-3 days', '+14 days'),
        ];
    }
}
