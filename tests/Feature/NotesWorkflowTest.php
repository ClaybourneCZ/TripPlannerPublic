<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_cards_include_ordered_stops_with_unnamed_and_duplicate_names(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'Walk']);
        foreach ([3 => null, 1 => 'Cafe', 0 => null, 2 => 'Cafe'] as $order => $name) {
            $trip->waypoints()->create(['name' => $name, 'point_lat' => 50, 'point_lng' => 14, 'order' => $order]);
        }
        $selected = $user->trips()->create(['name' => 'Selected']);
        $foreign = User::factory()->create()->trips()->create(['name' => 'Private']);
        $foreign->waypoints()->create(['name' => 'Private stop', 'point_lat' => 50, 'point_lng' => 14, 'order' => 0]);

        $this->actingAs($user)->get(route('notes.index', ['trip' => $selected->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('notes/Note')
                ->has('trips', 2)
                ->where('trips.0.id', $selected->id)
                ->where('trips.0.stops', [])
                ->where('trips.1.id', $trip->id)
                ->where('trips.1.stops', ['Unnamed stop', 'Cafe', 'Cafe', 'Unnamed stop'])
                ->missing('trips.1.waypoints')
                ->where('trip.id', $selected->id));
    }

    public function test_notes_are_saved_and_reloaded_without_changing_route_or_catalog(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'Walk']);
        $point = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        User::factory()->create()->trips()->create(['name' => 'Private']);
        $this->actingAs($user)->patch(route('notes.update', $trip), [
            'notes' => 'Weekend', 'tags' => ['Family'],
            'waypoints' => [['id' => $point->id, 'name' => 'Cafe', 'description' => 'Lunch stop', 'tags' => ['Lunch', 'Custom']]],
            'user_id' => 999, 'is_public' => true,
        ])->assertRedirect(route('notes.index', ['trip' => $trip->id]));
        $this->get(route('notes.index', ['trip' => $trip->id]))->assertInertia(fn (Assert $page) => $page
            ->component('notes/Note')->has('trips', 1)->where('trip.notes', 'Weekend')->where('trip.tags', ['Family'])
            ->where('trip.waypoints.0.description', 'Lunch stop')->where('trip.waypoints.0.name', 'Cafe')
            ->where('trip.waypoints.0.tags', ['Lunch', 'Custom'])->missing('trip.user_id'));
        $this->assertSame(50.0, $point->fresh()->point_lat);
        $this->assertFalse($trip->fresh()->is_public);
        $this->assertDatabaseCount('waypoint_tag_presets', 0);
    }

    public function test_foreign_and_missing_trips_are_not_accessible(): void
    {
        $trip = User::factory()->create()->trips()->create(['name' => 'Private']);
        $this->patch(route('notes.update', $trip), [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        foreach ([$trip->id, $trip->id + 1] as $id) {
            $this->get(route('notes.index', ['trip' => $id]))->assertNotFound();
            $this->patch(route('notes.update', $id), [])->assertNotFound();
        }
    }

    public function test_invalid_notes_and_foreign_waypoints_leave_data_unchanged(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'Walk', 'notes' => 'Original']);
        $other = $user->trips()->create(['name' => 'Other']);
        $point = $other->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        $this->actingAs($user)->patch(route('notes.update', $trip), [
            'notes' => str_repeat('x', 10001), 'tags' => ['Repeated', 'Repeated'],
            'waypoints' => [['id' => $point->id, 'name' => 'Changed', 'description' => '', 'tags' => []]],
        ])->assertSessionHasErrors(['notes', 'tags.0', 'waypoints.0.id']);
        $this->assertSame('Original', $trip->fresh()->notes);
        $this->assertNull($point->fresh()->name);
    }

    public function test_empty_account_has_no_sample_trip(): void
    {
        $this->actingAs(User::factory()->create())->get(route('notes.index'))->assertInertia(fn (Assert $page) => $page
            ->component('notes/Note')->where('trips', [])->where('trip', null));
    }
}
