<?php

namespace Database\Seeders;

use App\Models\WaypointTagPreset;
use Illuminate\Database\Seeder;

class WaypointTagPresetSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Restaurant', 'Lunch', 'Cafe', 'Accommodation', 'Viewpoint', 'Parking'] as $name) {
            WaypointTagPreset::firstOrCreate(['name' => $name]);
        }
    }
}
