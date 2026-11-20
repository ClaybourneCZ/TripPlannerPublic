<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TripDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_owned_trips_with_ordered_points_and_unknown_metrics(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Walk', 'notes' => 'Along the river']);
        $trip->waypoints()->create(['point_lat' => 51, 'point_lng' => 15, 'order' => 1]);
        $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0, 'name' => 'Start']);
        User::factory()->create()->trips()->create(['name' => 'Private elsewhere']);

        $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->has('trips.data', 1)
            ->where('trips.data.0.id', $trip->id)
            ->where('trips.data.0.notes', 'Along the river')
            ->missing('trips.data.0.summary')
            ->where('trips.data.0.distance', null)
            ->where('trips.data.0.duration', null)
            ->where('trips.data.0.points', [[50, 14], [51, 15]])
            ->where('trips.data.0.stops', ['Start', 'Unnamed stop'])
            ->missing('trips.data.0.user_id'));
    }

    public function test_dashboard_paginates_twelve_trips_and_empty_account_has_none(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('trips.data', []));
        foreach (range(1, 13) as $number) {
            $owner->trips()->create(['name' => "Trip {$number}"]);
        }
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('trips.data', 12)->where('trips.data.0.name', 'Trip 13'));
        $this->get(route('dashboard', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('trips.data', 1)->where('trips.data.0.name', 'Trip 1'));
    }

    public function test_removed_plans_endpoint_is_not_available(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Walk']);
        $this->actingAs($owner)->get('/plans')->assertNotFound();
        $this->get('/plans?trip='.$trip->id)->assertNotFound();
    }
}
