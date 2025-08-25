<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParticipationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teamUsers = User::where('role', 'team')->get();

        Event::with('season')->get()->each(function ($event) use ($teamUsers) {
            $participants = $teamUsers->random(min($teamUsers->count(), fake()->numberBetween(8, 15)));

            $rows = collect();
            foreach ($participants as $user) {
                $rows->push([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'total_points' => fake()->numberBetween(0, 100),
                    'rank' => null, // set below
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($rows->isNotEmpty()) {
                DB::table('participations')->insert($rows->toArray());

                $created = Participation::where('event_id', $event->id)
                    ->orderByDesc('total_points')
                    ->get();

                $rank = 0;
                $lastPoints = null;
                foreach ($created as $p) {
                    if ($lastPoints !== $p->total_points) {
                        $rank++;
                        $lastPoints = $p->total_points;
                    }
                    $p->rank = $rank;
                    $p->save();
                }

                if ($event->starts_at < now() || $event->status === 'completed') {
                    $event->status = 'completed';
                    $event->scores_finalized = true;
                    $event->save();
                }
            }
        });
    }
}
