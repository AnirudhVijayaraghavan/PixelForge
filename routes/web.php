<?php

use App\Http\Controllers\CreateGenerationController;
use App\Http\Controllers\GenerationIndexController;
use App\Http\Controllers\GenerationShowController;
use App\Http\Controllers\GenerationStatusesController;
use App\Http\Controllers\QuoteGenerationController;
use App\Http\Controllers\StudioController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (Request $request): Response|RedirectResponse {
    if ($request->user() !== null) {
        return to_route('studio');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::post('generations', CreateGenerationController::class)
    ->middleware('auth')
    ->name('generations.store');

Route::middleware('auth')->group(function (): void {
    Route::get('studio', StudioController::class)->name('studio');
    Route::get('generations', GenerationIndexController::class)->name('generations.index');
    Route::post('generations/quote', QuoteGenerationController::class)->name('generations.quote');
    Route::get('generations/statuses', GenerationStatusesController::class)->name('generations.statuses');
    Route::get('generations/{generation}', GenerationShowController::class)->whereUuid('generation')->name('generations.show');
    Route::get('dashboard', fn (): RedirectResponse => to_route('studio'))->name('dashboard');
});

require __DIR__.'/settings.php';
