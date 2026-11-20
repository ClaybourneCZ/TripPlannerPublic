<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotesRequest;
use App\Models\Trip;
use App\Models\Waypoint;
use App\Models\WaypointTagPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotesController extends Controller
{
    public function index(Request $request): Response
    {
        $trips = $request->user()->trips()->orderByDesc('updated_at')->orderByDesc('id')
            ->with(['waypoints' => fn ($query) => $query->select('id', 'trip_id', 'name', 'order')])
            ->get(['id', 'name', 'notes', 'distance', 'duration']);
        $selectedId = $request->query('trip', $trips->first()?->id);
        abort_if($selectedId !== null && (! is_scalar($selectedId) || ! ctype_digit((string) $selectedId)), 404);
        $trip = $selectedId === null ? null : $request->user()->trips()->findOrFail($selectedId);
        $trip?->load(['tags', 'waypoints.tags']);

        return Inertia::render('notes/Note', [
            'trips' => $trips->map(fn (Trip $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'notes' => $item->notes,
                'distance' => $item->distance,
                'duration' => $item->duration,
                'stops' => $item->waypoints->map(fn (Waypoint $point) => $point->name ?? 'Unnamed stop')->all(),
            ])->all(),
            'trip' => $trip === null ? null : [
                'id' => $trip->id,
                'name' => $trip->name,
                'notes' => $trip->notes,
                'distance' => $trip->distance,
                'duration' => $trip->duration,
                'tags' => $trip->tags->pluck('name')->all(),
                'waypoints' => $trip->waypoints->map(fn ($point) => [
                    'id' => $point->id,
                    'name' => $point->name,
                    'description' => $point->description,
                    'point_lat' => $point->point_lat,
                    'point_lng' => $point->point_lng,
                    'order' => $point->order,
                    'tags' => $point->tags->pluck('name')->all(),
                ])->all(),
            ],
            'waypointTagPresets' => WaypointTagPreset::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function update(UpdateNotesRequest $request, int $trip): RedirectResponse
    {
        $ownedTrip = $request->user()->trips()->findOrFail($trip);
        $input = $request->validated();
        DB::transaction(function () use ($ownedTrip, $input): void {
            $ownedTrip->update(['notes' => $input['notes']]);
            $ownedTrip->tags()->delete();
            foreach ($input['tags'] as $name) {
                $ownedTrip->tags()->create(['name' => $name]);
            }
            foreach ($input['waypoints'] as $point) {
                $waypoint = $ownedTrip->waypoints()->findOrFail($point['id']);
                $waypoint->update(['name' => $point['name'], 'description' => $point['description']]);
                $waypoint->tags()->delete();
                foreach (array_unique($point['tags']) as $name) {
                    $waypoint->tags()->create(['name' => $name]);
                }
            }
        });

        return to_route('notes.index', ['trip' => $trip]);
    }
}
