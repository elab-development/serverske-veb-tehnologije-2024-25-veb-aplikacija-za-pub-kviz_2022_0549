<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Season;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Season::all()->each(function ($season) {
            Event::factory()
                ->count(6)
                ->state(['season_id' => $season->id])
                ->create();
        });
    }
}
