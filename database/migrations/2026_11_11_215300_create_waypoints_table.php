<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->decimal('point_lng', 10, 7);
            $table->decimal('point_lat', 9, 7);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order');
            $table->timestamps();
            $table->unique(['trip_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waypoints');
    }
};
