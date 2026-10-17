<?php

use App\Http\Controllers\NotesController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RecommendationsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::resource('plans', PlanController::class)->middleware('auth');

Route::resource('recommendations', RecommendationsController::class)->middleware('auth');

Route::resource('notes', NotesController::class)->middleware('auth');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
