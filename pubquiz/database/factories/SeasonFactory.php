<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Season ' . fake()->numberBetween(date('Y') - 1, date('Y') + 1) . ' ' . fake()->randomElement(['Spring', 'Summer', 'Fall', 'Winter']);
        $start = fake()->dateTimeBetween('-3 months', '+1 month');
        $end = (clone $start);
        $end->modify('+3 months');

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'is_active' => fake()->boolean(60),
            'description' => fake()->sentence(12),
        ];
    }
}
