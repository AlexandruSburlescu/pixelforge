<?php

use App\Jobs\PumpGenerationQueue;
use App\Jobs\ReconcileGenerations;
use App\Jobs\SyncProviderUsage;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Safety nets: the pump and reconciler are normally woken by events, these make
// sure nothing stalls if a wake-up or a webhook is ever lost.
Schedule::job(new PumpGenerationQueue)->everyMinute();
Schedule::job(new ReconcileGenerations)->everyMinute();

// Pixelforge's own cost numbers for the admin report.
Schedule::job(new SyncProviderUsage(3))->hourly();

// Housekeeping.
Schedule::command('model:prune', ['--model' => [ApiLog::class]])->daily();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->daily();

Artisan::command('pixelforge:sync-usage {--days=30}', function () {
    SyncProviderUsage::dispatchSync((int) $this->option('days'));
    $this->info('Usage synced.');
})->purpose('Pull GET /v1/usage into provider_usage_daily now');
