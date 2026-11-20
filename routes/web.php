<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\RecommendationsController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified', 'feature:dashboard,page'])->name('dashboard');

Route::get('dashboard/export', function () {
    return Inertia::render('exports/Export');
})->middleware(['auth', 'verified', 'feature:export,page'])->name('dashboard.export');

Route::get('dashboard/publish', function () {
    return Inertia::render('publishing/Publish');
})->middleware(['auth', 'verified', 'feature:publish,page'])->name('dashboard.publish');

Route::middleware('auth')->group(function () {
    Route::get('trips/create', [TripController::class, 'create'])->middleware('feature:plan,page')->name('trips.create');
    Route::post('trips', [TripController::class, 'store'])->middleware('feature:plan')->name('trips.store');
    Route::get('trips/{trip}/edit', [TripController::class, 'edit'])->whereNumber('trip')->middleware('feature:plan,page')->name('trips.edit');
    Route::patch('trips/{trip}', [TripController::class, 'update'])->whereNumber('trip')->middleware('feature:plan')->name('trips.update');
    Route::delete('trips/{trip}', [TripController::class, 'destroy'])->whereNumber('trip')->middleware('feature:plan')->name('trips.destroy');
});

Route::resource('recommendations', RecommendationsController::class)->only('index')->middleware(['auth', 'feature:recommendations,page']);

Route::patch('notes/{trip}', [NotesController::class, 'update'])->middleware(['auth', 'feature:notes'])->whereNumber('trip')->name('notes.update');

Route::get('notes', [NotesController::class, 'index'])->middleware(['auth', 'feature:notes,page'])->name('notes.index');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
