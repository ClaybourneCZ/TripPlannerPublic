<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaypointTagPreset;
use Database\Seeders\WaypointTagPresetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaypointTagPresetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_notes_editor(): void
    {
        $this->get(route('notes.index'))->assertRedirect(route('login'));
    }

    public function test_notes_page_reads_catalog_and_keeps_owned_trip_selection(): void
    {
        WaypointTagPreset::create(['name' => 'Museum']);
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'My trip']);
        $this->actingAs($user)->get(route('notes.index', ['trip' => $trip->id]))
            ->assertInertia(fn (Assert $page) => $page->component('notes/Note')
                ->where('trip.id', $trip->id)->where('waypointTagPresets', ['Museum']));
    }

    public function test_empty_notes_catalog_does_not_create_presets(): void
    {
        $this->actingAs(User::factory()->create())->get(route('notes.index'))
            ->assertInertia(fn (Assert $page) => $page->component('notes/Note')
                ->where('waypointTagPresets', []));
        $this->assertDatabaseCount('waypoint_tag_presets', 0);
    }

    public function test_seeder_can_run_repeatedly_without_replacing_existing_entries(): void
    {
        $museum = WaypointTagPreset::create(['name' => 'Museum']);
        $this->seed(WaypointTagPresetSeeder::class);
        $restaurantId = WaypointTagPreset::where('name', 'Restaurant')->sole()->id;
        $this->seed(WaypointTagPresetSeeder::class);

        $this->assertDatabaseCount('waypoint_tag_presets', 7);
        $this->assertModelExists($museum);
        $this->assertSame($restaurantId, WaypointTagPreset::where('name', 'Restaurant')->sole()->id);
    }

    public function test_catalog_rejects_duplicate_names(): void
    {
        WaypointTagPreset::create(['name' => 'Lunch']);

        $this->expectException(QueryException::class);
        WaypointTagPreset::create(['name' => 'Lunch']);
    }

    public function test_catalog_migration_preserves_existing_waypoint_assignments(): void
    {
        $trip = User::factory()->create()->trips()->create(['name' => 'Existing trip']);
        $waypoint = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0]);
        $tag = $waypoint->tags()->create(['name' => 'Existing custom tag']);
        $this->seed(WaypointTagPresetSeeder::class);

        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('waypoint_tag_presets'));
        $this->assertModelExists($tag);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(WaypointTagPresetSeeder::class);

        $this->assertModelExists($waypoint);
        $this->assertSame('Existing custom tag', $tag->fresh()->name);
        $this->assertDatabaseCount('waypoint_tag_presets', 6);
    }

    public function test_third_waypoint_can_have_preset_and_custom_tags_without_changing_other_points(): void
    {
        $this->seed(WaypointTagPresetSeeder::class);
        $catalogBefore = WaypointTagPreset::query()->orderBy('id')->get()->toArray();
        $trip = User::factory()->create()->trips()->create(['name' => 'Day trip']);
        foreach (range(0, 2) as $order) {
            $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => $order]);
        }

        $waypoints = $trip->waypoints()->get();
        $third = $waypoints->last();
        $selectedNames = WaypointTagPreset::query()->whereIn('name', ['Restaurant', 'Lunch'])->pluck('name')->all();
        foreach ([...$selectedNames, 'Birthday stop'] as $name) {
            $third->tags()->create(['name' => $name]);
        }

        $this->assertEqualsCanonicalizing(
            ['Restaurant', 'Lunch', 'Birthday stop'],
            $third->fresh()->tags->pluck('name')->all(),
        );
        $this->assertCount(0, $waypoints[0]->tags);
        $this->assertCount(0, $waypoints[1]->tags);
        $this->assertDatabaseHas('waypoint_tags', ['waypoint_id' => $third->id, 'name' => 'Birthday stop']);
        $this->assertDatabaseMissing('waypoint_tag_presets', ['name' => 'Birthday stop']);
        $third->tags()->where('name', 'Lunch')->delete();
        $this->assertEqualsCanonicalizing(['Restaurant', 'Birthday stop'], $third->fresh()->tags->pluck('name')->all());
        $this->assertSame($catalogBefore, WaypointTagPreset::query()->orderBy('id')->get()->toArray());
    }
}
