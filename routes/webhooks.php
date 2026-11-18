<?php

use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

// Loaded without the "web" middleware group: no session, cookies or CSRF.
// Pixelforge proves itself with the HMAC signature instead.
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.pixelforge');
