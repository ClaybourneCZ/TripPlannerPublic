<?php

namespace App\Application\Trips;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTrip
{
    /** @param array{name: string, waypoints: list<array{point_lat: float|int|string, point_lng: float|int|string, name?: string|null}>} $input */
    public function execute(User $owner, array $input): Trip
    {
        return DB::transaction(function () use ($owner, $input): Trip {
            $trip = $owner->trips()->create(['name' => $input['name']]);
            foreach ($input['waypoints'] as $order => $point) {
                $trip->waypoints()->create([
                    'point_lat' => $point['point_lat'],
                    'point_lng' => $point['point_lng'],
                    'order' => $order,
                    'name' => $point['name'] ?? null,
                ]);
            }

            return $trip;
        });
    }
}
