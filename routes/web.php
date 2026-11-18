<?php

use App\Http\Controllers\CreateGenerationController;
use App\Http\Controllers\GenerationIndexController;
use App\Http\Controllers\GenerationShowController;
use App\Http\Controllers\GenerationStatusesController;
use App\Http\Controllers\QuoteGenerationController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('generations', CreateGenerationController::class)
    ->middleware('auth')
    ->name('generations.store');

Route::middleware('auth')->group(function (): void {
    Route::get('studio', StudioController::class)->name('studio');
    Route::get('generations', GenerationIndexController::class)->name('generations.index');
    Route::post('generations/quote', QuoteGenerationController::class)->name('generations.quote');
    Route::get('generations/statuses', GenerationStatusesController::class)->name('generations.statuses');
    Route::get('generations/{generation}', GenerationShowController::class)->whereUuid('generation')->name('generations.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', StudioController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
