<?php

use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Email verification is not required: credits are fake and every account gets
// the same small welcome balance, so an unverified account can't cost real money.
Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/generate')->name('dashboard');

    Route::get('generate', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');
    Route::get('history', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('generations/status', [GenerationController::class, 'status'])
        ->middleware('throttle:status')
        ->name('generations.status');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'purchase'])
        ->middleware('throttle:generations')
        ->name('credits.purchase');
});

require __DIR__.'/settings.php';
