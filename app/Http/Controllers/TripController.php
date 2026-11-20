<?php

namespace App\Http\Controllers;

use App\Application\Trips\CreateTrip;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use App\Support\AvailablePageResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('trips/Editor', ['trip' => null]);
    }

    public function store(StoreTripRequest $request, CreateTrip $create): RedirectResponse
    {
        $trip = $create->execute($request->user(), $request->validated());

        return to_route('trips.edit', $trip->id)->with('success', 'Trip saved.');
    }

    public function edit(Request $request, int $trip): Response
    {
        $ownedTrip = $this->ownedTrip($request, $trip);
        $ownedTrip->load(['waypoints' => fn ($query) => $query->select('id', 'trip_id', 'point_lat', 'point_lng', 'name', 'order'), 'tags:id,trip_id,name']);

        return Inertia::render('trips/Editor', [
            'notice' => $request->session()->get('success'),
            'trip' => [
                'id' => $ownedTrip->id,
                'name' => $ownedTrip->name,
                'notes' => $ownedTrip->notes,
                'waypoints' => $ownedTrip->waypoints->map(fn ($point) => [
                    'id' => $point->id,
                    'point_lat' => $point->point_lat,
                    'point_lng' => $point->point_lng,
                    'name' => $point->name,
                ])->all(),
                'tags' => $ownedTrip->tags->pluck('name')->all(),
            ],
        ]);
    }

    public function update(UpdateTripRequest $request, int $trip): RedirectResponse
    {
        $ownedTrip = $this->ownedTrip($request, $trip);
        $input = $request->validated();
        DB::transaction(function () use ($ownedTrip, $input): void {
            $ownedTrip->update(['name' => $input['name'], 'notes' => $input['notes']]);
            $ownedTrip->tags()->delete();
            foreach ($input['tags'] as $name) {
                $ownedTrip->tags()->create(['name' => $name]);
            }
            if (array_key_exists('waypoints', $input)) {
                $this->updateWaypoints($ownedTrip, $input['waypoints']);
            }
        });

        return to_route('trips.edit', $trip)->with('success', 'Trip updated.');
    }

    public function destroy(Request $request, int $trip): RedirectResponse
    {
        $this->ownedTrip($request, $trip)->delete();

        return redirect(app(AvailablePageResolver::class)->entryUrl($request))->with('success', 'Trip deleted.');
    }

    /** @param list<array{id?: int, point_lat: float|int|string, point_lng: float|int|string, name?: string|null}> $points */
    private function updateWaypoints(Trip $trip, array $points): void
    {
        $existing = $trip->waypoints()->get()->keyBy('id');
        $retainedIds = collect($points)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $trip->waypoints()->whereNotIn('id', $retainedIds)->delete();

        // Move retained rows beyond the final range before assigning new order values.
        $temporaryOrder = max($existing->max('order') ?? 0, count($points)) + 1;
        foreach ($existing as $waypoint) {
            if (in_array($waypoint->id, $retainedIds, true)) {
                $waypoint->update(['order' => $temporaryOrder++]);
            }
        }

        foreach ($points as $order => $point) {
            $values = [
                'point_lat' => $point['point_lat'],
                'point_lng' => $point['point_lng'],
                'name' => $point['name'] ?? null,
                'order' => $order,
            ];
            if (isset($point['id'])) {
                $existing->get((int) $point['id'])->update($values);
            } else {
                $trip->waypoints()->create($values);
            }
        }
    }

    private function ownedTrip(Request $request, int $trip): Trip
    {
        return $request->user()->trips()->findOrFail($trip);
    }
}
