<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+2 months');
        $end = (clone $start);
        $end->modify('+2 hours');

        return [
            'season_id' => Season::factory(),
            'title' => fake()->city() . ' Pub Quiz',
            'location' => fake()->address(),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => fake()->randomElement(['scheduled', 'completed', 'cancelled']),
            'scores_finalized' => false,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn() => ['status' => 'scheduled', 'scores_finalized' => false]);
    }

    public function completed(): static
    {
        return $this->state(fn() => ['status' => 'completed', 'scores_finalized' => true]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => ['status' => 'cancelled', 'scores_finalized' => false]);
    }
}
