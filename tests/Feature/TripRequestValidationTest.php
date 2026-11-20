<?php

namespace Tests\Feature;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TripRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The public trip write endpoints are part of step 3. Exercise the
        // actual Form Requests at an HTTP boundary without exposing placeholder writes.
        Route::middleware('auth')->post('/_test/trips', function (StoreTripRequest $request) {
            return response()->json($request->validated());
        });
        Route::middleware('auth')->patch('/_test/trips/{trip}', function (UpdateTripRequest $request) {
            return response()->json($request->validated());
        });
    }

    private function createInput(): array
    {
        return [
            'name' => 'Prague walk',
            'waypoints' => [
                ['point_lat' => 50.0755381, 'point_lng' => 14.4378005],
                ['point_lat' => -90, 'point_lng' => 180],
            ],
        ];
    }

    public function test_authentication_is_required_for_both_requests(): void
    {
        $this->post('/_test/trips', $this->createInput())->assertRedirect(route('login'));
        $this->patch('/_test/trips/1', ['name' => 'Name', 'notes' => null, 'tags' => []])
            ->assertRedirect(route('login'));
    }

    public function test_store_accepts_valid_points_and_excludes_untrusted_fields(): void
    {
        $input = $this->createInput() + ['user_id' => 999, 'is_public' => true, 'distance' => 900];
        $this->actingAs(User::factory()->create())->postJson('/_test/trips', $input)
            ->assertOk()->assertExactJson($this->createInput());
    }

    public function test_store_rejects_invalid_coordinates_and_point_keys(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            ['point_lat' => 90.0000001, 'point_lng' => 14],
            ['point_lat' => -90.0000001, 'point_lng' => 14],
            ['point_lat' => 50, 'point_lng' => 180.0000001],
            ['point_lat' => 50, 'point_lng' => -180.0000001],
            ['point_lat' => 'invalid', 'point_lng' => 14],
            ['point_lat' => 50, 'point_lng' => 14, 'user_id' => 99],
        ] as $point) {
            $input = $this->createInput();
            $input['waypoints'][0] = $point;
            $this->postJson('/_test/trips', $input)->assertUnprocessable();
        }
    }

    public function test_store_rejects_invalid_name_and_point_counts(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['', '   ', str_repeat('x', 256)] as $name) {
            $input = $this->createInput();
            $input['name'] = $name;
            $this->postJson('/_test/trips', $input)->assertUnprocessable();
        }
        foreach ([[], [$this->createInput()['waypoints'][0]], array_fill(0, 101, $this->createInput()['waypoints'][0])] as $points) {
            $input = $this->createInput();
            $input['waypoints'] = $points;
            $this->postJson('/_test/trips', $input)->assertUnprocessable();
        }
    }

    public function test_update_only_accepts_own_trip_and_valid_metadata(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Original']);
        $payload = ['name' => 'New name', 'notes' => 'Lunch stop', 'tags' => ['Restaurant', 'Custom']];
        $this->actingAs($owner)->patchJson("/_test/trips/{$trip->id}", $payload + ['user_id' => 999, 'is_public' => true])
            ->assertOk()->assertExactJson($payload);
        $this->assertSame('Original', $trip->fresh()->name);

        $this->actingAs(User::factory()->create())->patchJson("/_test/trips/{$trip->id}", $payload)->assertNotFound();
        $this->patchJson('/_test/trips/99999', $payload)->assertNotFound();
    }

    public function test_update_rejects_invalid_metadata(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Original']);
        $this->actingAs($owner);
        $base = ['name' => 'Name', 'notes' => null, 'tags' => []];
        foreach ([
            ['name' => ' '], ['notes' => str_repeat('a', 10001)],
            ['tags' => array_fill(0, 21, 'Tag')], ['tags' => ['Lunch', ' Lunch ']],
            ['tags' => ['']], ['tags' => [str_repeat('a', 51)]], ['tags' => [['name' => 'Cafe']]],
        ] as $change) {
            $this->patchJson("/_test/trips/{$trip->id}", array_replace($base, $change))->assertUnprocessable();
        }
        $this->patchJson("/_test/trips/{$trip->id}", ['name' => 'Name', 'tags' => []])->assertUnprocessable();
    }
}
