<?php

use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\GenerationFinancialController;
use App\Http\Controllers\Admin\RefreshReconciliationController;
use App\Http\Controllers\CreateGenerationController;
use App\Http\Controllers\GenerationIndexController;
use App\Http\Controllers\GenerationShowController;
use App\Http\Controllers\GenerationStatusesController;
use App\Http\Controllers\QuoteGenerationController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\WalletController;
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
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('wallet/purchases', [WalletController::class, 'purchase'])->name('wallet.purchase');
    Route::get('studio', StudioController::class)->name('studio');
    Route::get('generations', GenerationIndexController::class)->name('generations.index');
    Route::post('generations/quote', QuoteGenerationController::class)->name('generations.quote');
    Route::get('generations/statuses', GenerationStatusesController::class)->name('generations.statuses');
    Route::get('generations/{generation}', GenerationShowController::class)->whereUuid('generation')->name('generations.show');
    Route::get('dashboard', fn (): RedirectResponse => to_route('studio'))->name('dashboard');
});

require __DIR__.'/settings.php';

Route::middleware(['auth', 'can:viewFinancialReports'])->prefix('admin/financials')->name('admin.financials.')->group(function (): void {
    Route::get('/', FinancialReportController::class)->name('index');
    Route::post('reconciliation', RefreshReconciliationController::class)->name('refresh');
    Route::get('generations/{generation}', GenerationFinancialController::class)->whereUuid('generation')->name('show');
});
