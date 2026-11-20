<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Waypoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $trips = $request->user()->trips()
            ->select(['id', 'name', 'notes', 'distance', 'duration', 'updated_at'])
            ->with(['waypoints' => fn ($query) => $query->select('id', 'trip_id', 'point_lat', 'point_lng', 'name', 'order')])
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(12)->through(fn (Trip $trip) => [
                'id' => $trip->id,
                'name' => $trip->name,
                'notes' => $trip->notes,
                'distance' => $trip->distance,
                'duration' => $trip->duration,
                'points' => $trip->waypoints->map(fn (Waypoint $point) => [$point->point_lat, $point->point_lng])->all(),
                'stops' => $trip->waypoints->map(fn (Waypoint $point) => $point->name ?? 'Unnamed stop')->all(),
            ]);

        return Inertia::render('Dashboard', [
            'trips' => $trips,
            'notice' => $request->session()->get('success'),
        ]);
    }
}
