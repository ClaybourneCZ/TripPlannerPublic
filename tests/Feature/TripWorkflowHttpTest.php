<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TripWorkflowHttpTest extends TestCase
{
    use RefreshDatabase;

    private function input(): array
    {
        return ['name' => 'Walk', 'waypoints' => [
            ['point_lat' => 50, 'point_lng' => 14, 'name' => 'Start'],
            ['point_lat' => 51, 'point_lng' => 15, 'name' => 'Finish'],
        ]];
    }

    public function test_create_reload_update_and_delete_owned_trip(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('trips.store'), $this->input() + ['user_id' => 999, 'is_public' => true])
            ->assertRedirect();
        $trip = $user->trips()->sole();
        $this->assertFalse($trip->is_public);
        $this->assertSame([0, 1], $trip->waypoints->pluck('order')->all());
        $this->assertSame(['Start', 'Finish'], $trip->waypoints->pluck('name')->all());
        $this->get(route('trips.edit', $trip))->assertInertia(fn (Assert $page) => $page
            ->component('trips/Editor')->where('trip.name', 'Walk')
            ->where('trip.waypoints.1.point_lat', 51)->where('trip.waypoints.1.name', 'Finish')->missing('trip.user_id'));
        $this->patch(route('trips.update', $trip), [
            'name' => 'Long walk', 'notes' => 'Bring water', 'tags' => ['Lunch', 'Custom'],
            'user_id' => 999, 'is_public' => true,
        ])->assertRedirect(route('trips.edit', $trip));
        $this->assertSame('Long walk', $trip->fresh()->name);
        $this->assertSame('Bring water', $trip->fresh()->notes);
        $this->assertEqualsCanonicalizing(['Lunch', 'Custom'], $trip->fresh()->tags->pluck('name')->all());
        $this->assertFalse($trip->fresh()->is_public);
        $this->delete(route('trips.destroy', $trip))->assertRedirect(route('dashboard'));
        $this->assertModelMissing($trip);
        $this->get(route('trips.edit', $trip))->assertNotFound();
    }

    public function test_owned_trip_can_add_remove_and_rename_waypoints_without_losing_retained_details(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Walk']);
        $removed = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        $kept = $trip->waypoints()->create(['point_lat' => 51, 'point_lng' => 15, 'order' => 1, 'description' => 'Keep this']);
        $tag = $kept->tags()->create(['name' => 'Cafe']);

        $this->actingAs($owner)->patch(route('trips.update', $trip), [
            'name' => 'Walk', 'notes' => null, 'tags' => [],
            'waypoints' => [
                ['id' => $kept->id, 'point_lat' => 51, 'point_lng' => 15, 'name' => 'Rest stop'],
                ['point_lat' => 52, 'point_lng' => 16, 'name' => 'Finish'],
            ],
        ])->assertRedirect(route('trips.edit', $trip));

        $this->assertModelMissing($removed);
        $this->assertModelExists($tag);
        $this->assertSame('Keep this', $kept->fresh()->description);
        $this->assertSame(['Rest stop', 'Finish'], $trip->waypoints()->pluck('name')->all());
        $this->get(route('trips.edit', $trip))->assertInertia(fn (Assert $page) => $page
            ->where('trip.waypoints.0.id', $kept->id)->where('trip.waypoints.1.name', 'Finish'));
    }

    public function test_waypoint_update_rejects_foreign_ids_and_invalid_route(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Walk']);
        $point = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        $foreignTrip = User::factory()->create()->trips()->create(['name' => 'Other']);
        $foreignPoint = $foreignTrip->waypoints()->create(['point_lat' => 51, 'point_lng' => 15, 'order' => 0]);
        $payload = ['name' => 'Changed', 'notes' => null, 'tags' => [], 'waypoints' => [
            ['id' => $point->id, 'point_lat' => 50, 'point_lng' => 14],
            ['id' => $foreignPoint->id, 'point_lat' => 51, 'point_lng' => 15],
        ]];
        $this->actingAs($owner)->patch(route('trips.update', $trip), $payload)->assertSessionHasErrors('waypoints');
        $this->assertSame('Walk', $trip->fresh()->name);
        $payload['waypoints'] = [['id' => $point->id, 'point_lat' => 50, 'point_lng' => 14]];
        $this->patch(route('trips.update', $trip), $payload)->assertSessionHasErrors('waypoints');
        $this->assertModelExists($point);
    }

    public function test_guest_and_foreign_trip_access_is_denied(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Private']);
        $this->get(route('trips.create'))->assertRedirect(route('login'));
        $this->post(route('trips.store'), $this->input())->assertRedirect(route('login'));
        $this->get(route('trips.edit', $trip))->assertRedirect(route('login'));
        $this->patch(route('trips.update', $trip), ['name' => 'X', 'notes' => null, 'tags' => []])->assertRedirect(route('login'));
        $this->delete(route('trips.destroy', $trip))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('trips.edit', $trip))->assertNotFound();
        $this->patch(route('trips.update', $trip), ['name' => 'X', 'notes' => null, 'tags' => []])->assertNotFound();
        $this->delete(route('trips.destroy', $trip))->assertNotFound();
        $this->assertModelExists($trip);
    }

    public function test_invalid_create_and_update_do_not_change_database(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('trips.store'), ['name' => ' ', 'waypoints' => []])->assertSessionHasErrors();
        $this->assertDatabaseCount('trips', 0);
        $invalid = $this->input();
        $invalid['waypoints'][0]['name'] = str_repeat('x', 256);
        $this->post(route('trips.store'), $invalid)->assertSessionHasErrors('waypoints.0.name');
        $this->assertDatabaseCount('trips', 0);
        $trip = $owner->trips()->create(['name' => 'Original']);
        $this->patch(route('trips.update', $trip), ['name' => 'Changed', 'notes' => null, 'tags' => ['Cafe', ' Cafe ']])->assertSessionHasErrors();
        $this->assertSame('Original', $trip->fresh()->name);
        $this->assertDatabaseCount('trip_tags', 0);
    }
}
