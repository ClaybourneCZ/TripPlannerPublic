<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TripNotesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_legacy_text_and_rollback_keeps_merged_notes(): void
    {
        $migration = require database_path('migrations/2026_11_17_000001_merge_trip_summary_into_notes.php');
        $migration->down();
        $user = User::factory()->create();
        $cases = [
            [null, null, null],
            ['River walk', null, 'River walk'],
            ['River walk', '', 'River walk'],
            [null, 'Bring water', 'Bring water'],
            ['', 'Bring water', 'Bring water'],
            ['River walk', 'Bring water', "Bring water\n\nRiver walk"],
            ['River walk', 'River walk', 'River walk'],
        ];
        $trips = [];
        foreach ($cases as [$summary, $notes, $expected]) {
            $trip = $user->trips()->create(['name' => 'Walk', 'notes' => $notes]);
            DB::table('trips')->where('id', $trip->id)->update(['summary' => $summary]);
            $trips[] = [$trip, $expected];
        }

        $migration->up();

        $this->assertFalse(Schema::hasColumn('trips', 'summary'));
        foreach ($trips as [$trip, $expected]) {
            $this->assertSame($expected, $trip->fresh()->notes);
        }

        $migration->down();
        $this->assertTrue(Schema::hasColumn('trips', 'summary'));
        foreach ($trips as [$trip, $expected]) {
            $this->assertSame($expected, $trip->fresh()->notes);
            $this->assertNull($trip->fresh()->summary);
        }
        $migration->up();
    }
}
