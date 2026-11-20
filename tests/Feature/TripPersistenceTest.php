<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripTag;
use App\Models\User;
use App\Models\Waypoint;
use App\Models\WaypointTag;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_migrations_can_be_rolled_back_and_reapplied(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 7, '--force' => true])->assertSuccessful();

        $this->assertFalse(Schema::hasTable('trip_tags'));
        $this->assertFalse(Schema::hasTable('waypoint_tags'));
        $this->assertFalse(Schema::hasTable('waypoints'));
        $this->assertFalse(Schema::hasTable('trips'));
        $this->assertTrue(Schema::hasTable('users'));

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $trip = User::factory()->create()->trips()->create(['name' => 'After rollback']);
        $this->assertModelExists($trip);
    }

    public function test_trip_graph_can_be_saved_and_loaded_in_route_order(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create([
            'name' => 'Prague walk',
            'notes' => 'Along the river',
            'distance' => 4200,
            'duration' => 3600,
        ]);
        $last = $trip->waypoints()->create([
            'point_lat' => 50.0755381,
            'point_lng' => 14.4378005,
            'name' => 'Finish',
            'description' => 'Meet here',
            'order' => 1,
        ]);
        $first = $trip->waypoints()->create([
            'point_lat' => -90,
            'point_lng' => -180,
            'order' => 0,
        ]);
        $tag = $last->tags()->create(['name' => 'Viewpoint']);

        $trip->refresh();
        $this->assertTrue($trip->user->is($user));
        $this->assertTrue($user->trips->sole()->is($trip));
        $this->assertSame('Along the river', $trip->notes);
        $this->assertSame(4200, $trip->distance);
        $this->assertSame(3600, $trip->duration);
        $this->assertFalse($trip->is_public);
        $this->assertSame([$first->id, $last->id], $trip->waypoints->modelKeys());
        $this->assertTrue($last->trip->is($trip));
        $this->assertSame(50.0755381, $last->fresh()->point_lat);
        $this->assertSame(14.4378005, $last->fresh()->point_lng);
        $this->assertSame(1, $last->fresh()->order);
        $this->assertNull($first->fresh()->name);
        $this->assertTrue($last->tags->sole()->is($tag));
        $this->assertTrue($tag->waypoint->is($last));
    }

    public function test_ownership_and_visibility_cannot_be_mass_assigned(): void
    {
        $trip = new Trip(['name' => 'Private trip', 'user_id' => 123, 'is_public' => true]);
        $waypoint = new Waypoint(['trip_id' => 123]);
        $tag = new WaypointTag(['name' => 'Cafe', 'waypoint_id' => 123]);
        $tripTag = new TripTag(['name' => 'Walking', 'trip_id' => 123]);

        $this->assertNull($trip->user_id);
        $this->assertNull($trip->is_public);
        $this->assertNull($waypoint->trip_id);
        $this->assertNull($tag->waypoint_id);
        $this->assertNull($tripTag->trip_id);

        $trip->user()->associate(User::factory()->create());
        $trip->save();
        $this->assertFalse($trip->fresh()->is_public);
        $this->assertNull($trip->fresh()->distance);
        $this->assertNull($trip->fresh()->duration);
    }

    public function test_deleting_an_owner_removes_their_trip_graph_only(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'Trip']);
        $waypoint = $trip->waypoints()->create(['point_lat' => 90, 'point_lng' => 180, 'order' => 0]);
        $tag = $waypoint->tags()->create(['name' => 'Stop']);
        $tripTag = $trip->tags()->create(['name' => 'Walking']);
        $otherTrip = User::factory()->create()->trips()->create(['name' => 'Other trip']);

        $owner->delete();

        $this->assertModelMissing($trip);
        $this->assertModelMissing($waypoint);
        $this->assertModelMissing($tag);
        $this->assertModelMissing($tripTag);
        $this->assertModelExists($otherTrip);
    }

    public function test_waypoint_order_is_unique_within_each_trip(): void
    {
        $owner = User::factory()->create();
        $trip = $owner->trips()->create(['name' => 'First trip']);
        $otherTrip = $owner->trips()->create(['name' => 'Second trip']);
        $attributes = ['point_lat' => 50, 'point_lng' => 14, 'order' => 0];
        $trip->waypoints()->create($attributes);
        $otherTrip->waypoints()->create($attributes);

        $this->expectException(QueryException::class);
        $trip->waypoints()->create($attributes);
    }

    #[DataProvider('orphanModels')]
    public function test_foreign_keys_reject_orphans(string $modelClass, string $foreignKey, array $attributes): void
    {
        $model = new $modelClass($attributes);
        $model->{$foreignKey} = 999;

        $this->expectException(QueryException::class);
        $model->save();
    }

    public static function orphanModels(): array
    {
        return [
            'trip' => [Trip::class, 'user_id', ['name' => 'Orphan']],
            'waypoint' => [Waypoint::class, 'trip_id', ['point_lat' => 50, 'point_lng' => 14, 'order' => 0]],
            'tag' => [WaypointTag::class, 'waypoint_id', ['name' => 'Orphan']],
            'trip tag' => [TripTag::class, 'trip_id', ['name' => 'Orphan']],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_invalid_coordinates_are_rejected(string $attribute, mixed $value): void
    {
        $trip = User::factory()->create()->trips()->create(['name' => 'Trip']);
        $attributes = ['point_lat' => 50, 'point_lng' => 14, 'order' => 0];
        $attributes[$attribute] = $value;

        $this->expectException(InvalidArgumentException::class);
        $trip->waypoints()->create($attributes);
    }

    public static function invalidCoordinates(): array
    {
        return [
            'north' => ['point_lat', 90.0000001],
            'south' => ['point_lat', -90.0000001],
            'east' => ['point_lng', 180.0000001],
            'west' => ['point_lng', -180.0000001],
            'infinite' => ['point_lat', INF],
            'not a number' => ['point_lng', NAN],
            'text' => ['point_lat', 'invalid'],
            'missing' => ['point_lng', null],
        ];
    }
}
