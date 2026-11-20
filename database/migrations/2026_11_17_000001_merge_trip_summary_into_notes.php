<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('trips')->whereNotNull('summary')->orderBy('id')->chunkById(100, function ($trips) {
            foreach ($trips as $trip) {
                if ($trip->summary === '' || $trip->summary === $trip->notes) {
                    continue;
                }

                $notes = $trip->notes === null || $trip->notes === ''
                    ? $trip->summary
                    : $trip->notes."\n\n".$trip->summary;

                DB::table('trips')->where('id', $trip->id)->update(['notes' => $notes]);
            }
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->text('summary')->nullable();
        });
    }
};
