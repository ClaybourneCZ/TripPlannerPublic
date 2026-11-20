<?php

namespace Tests\Feature;

use App\Models\TripTag;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class TripCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_update_survives_reload_without_changing_waypoints(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Before']);
        $point = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        $trip->tags()->create(['name' => 'Old']);

        $this->actingAs($owner)->patch(route('trips.update', $trip), [
            'name' => 'After', 'notes' => 'Bring water', 'tags' => ['Lunch', 'Walking'],
        ])->assertRedirect(route('trips.edit', $trip));

        $this->get(route('trips.edit', $trip))->assertInertia(fn (Assert $page) => $page
            ->component('trips/Editor')
            ->where('trip.name', 'After')
            ->where('trip.notes', 'Bring water')
            ->where('trip.tags', ['Lunch', 'Walking'])
            ->where('trip.waypoints.0.point_lat', 50));
        $this->assertSame(14.0, $point->fresh()->point_lng);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('trips.data.0.name', 'After'));
    }

    public function test_failed_tag_write_rolls_back_metadata_and_previous_tags(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Before']);
        $oldTag = $trip->tags()->create(['name' => 'Old']);
        $event = 'eloquent.creating: '.TripTag::class;
        Event::listen($event, function (TripTag $tag): void {
            if ($tag->name === 'Fail') {
                throw new RuntimeException('Write failed');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->patch(route('trips.update', $trip), [
                'name' => 'After', 'notes' => 'New notes', 'tags' => ['Walking', 'Fail'],
            ]);
            $this->fail('Expected write failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Write failed', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('Before', $trip->fresh()->name);
        $this->assertNull($trip->fresh()->notes);
        $this->assertModelExists($oldTag);
        $this->assertDatabaseCount('trip_tags', 1);
    }

    public function test_failed_waypoint_write_rolls_back_trip_creation(): void
    {
        $owner = User::factory()->create();
        $event = 'eloquent.creating: '.Waypoint::class;
        Event::listen($event, function (Waypoint $waypoint): void {
            if ($waypoint->order === 1) {
                throw new RuntimeException('Point failed');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->post(route('trips.store'), [
                'name' => 'Walk', 'waypoints' => [
                    ['point_lat' => 50, 'point_lng' => 14],
                    ['point_lat' => 51, 'point_lng' => 15],
                ],
            ]);
            $this->fail('Expected write failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Point failed', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('waypoints', 0);
    }

    public function test_deletion_removes_graph_and_shows_confirmation_without_touching_other_users(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Delete me']);
        $point = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        $waypointTag = $point->tags()->create(['name' => 'Cafe']);
        $tripTag = $trip->tags()->create(['name' => 'Walking']);
        $otherTrip = User::factory()->create()->trips()->create(['name' => 'Keep me']);
        $this->actingAs($owner)->delete(route('trips.destroy', $trip))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('notice', 'Trip deleted.')
            ->where('trips.data', []));
        foreach ([$trip, $point, $waypointTag, $tripTag] as $model) {
            $this->assertModelMissing($model);
        }
        $this->assertModelExists($otherTrip);
        $this->get(route('trips.edit', $trip))->assertNotFound();
    }
}
