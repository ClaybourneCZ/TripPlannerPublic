<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TripMetadataPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_and_trip_tags_can_be_saved_separately_from_waypoint_details(): void
    {
        $trip = User::factory()->create()->trips()->create(['name' => 'Walk']);
        $this->assertNull($trip->fresh()->notes);

        $trip->update(['notes' => "Bring water.\nMeet at noon."]);
        $tag = $trip->tags()->create(['name' => 'Walking']);
        $waypoint = $trip->waypoints()->create([
            'point_lat' => 50, 'point_lng' => 14, 'order' => 0, 'description' => 'Meeting point',
        ]);
        $waypointTag = $waypoint->tags()->create(['name' => 'Cafe']);

        $trip->refresh();
        $this->assertSame("Bring water.\nMeet at noon.", $trip->notes);
        $this->assertTrue($trip->tags->sole()->is($tag));
        $this->assertTrue($tag->trip->is($trip));
        $this->assertSame('Meeting point', $waypoint->fresh()->description);
        $this->assertTrue($waypoint->tags->sole()->is($waypointTag));

        $trip->update(['notes' => null]);
        $this->assertNull($trip->fresh()->notes);
    }

    public function test_tag_names_are_unique_within_a_trip_only(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'First']);
        $otherTrip = $user->trips()->create(['name' => 'Second']);
        $trip->tags()->create(['name' => 'Walking']);
        $otherTag = $otherTrip->tags()->create(['name' => 'Walking']);
        $this->assertModelExists($otherTag);

        $this->expectException(QueryException::class);
        $trip->tags()->create(['name' => 'Walking']);
    }

    public function test_deleting_a_trip_removes_only_its_tags(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'First']);
        $otherTrip = $user->trips()->create(['name' => 'Second']);
        $tag = $trip->tags()->create(['name' => 'Walking']);
        $otherTag = $otherTrip->tags()->create(['name' => 'Walking']);

        $trip->delete();

        $this->assertModelMissing($tag);
        $this->assertModelExists($otherTrip);
        $this->assertModelExists($otherTag);
    }

    public function test_metadata_migrations_preserve_existing_trips_and_waypoints(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 4, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('trips', 'notes'));
        $this->assertFalse(Schema::hasTable('trip_tags'));

        $trip = User::factory()->create()->trips()->create(['name' => 'Existing trip']);
        $waypoint = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame('Existing trip', $trip->fresh()->name);
        $this->assertNull($trip->fresh()->notes);
        $this->assertModelExists($waypoint);
        $trip->update(['notes' => 'New notes']);
        $trip->tags()->create(['name' => 'Walking']);

        $this->artisan('migrate:rollback', ['--step' => 4, '--force' => true])->assertSuccessful();
        $this->assertModelExists($trip);
        $this->assertModelExists($waypoint);
        $this->assertFalse(Schema::hasColumn('trips', 'notes'));
        $this->assertFalse(Schema::hasTable('trip_tags'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }
}
