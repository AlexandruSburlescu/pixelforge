<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Services\Pixelforge\PixelforgeClientFactory;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stores this environment's Pixelforge key + webhook secret encrypted in api_keys.
 * Reads PIXELFORGE_API_KEY / PIXELFORGE_WEBHOOK_SECRET if set, otherwise asks
 * with hidden input (so nothing lands in shell history).
 */
#[Signature('pixelforge:key {--name= : Label for the key} {--environment= : Defaults to PIXELFORGE_ENVIRONMENT}')]
#[Description('Store (and verify) the Pixelforge API key and webhook signing secret for this environment.')]
class StorePixelforgeKey extends Command
{
    public function handle(PixelforgeClientFactory $clients): int
    {
        $environment = $this->option('environment') ?: (string) config('pixelforge.environment');
        $secret = config('pixelforge.api_key') ?: $this->secret('Pixelforge API key');
        $webhookSecret = config('pixelforge.webhook_secret') ?: $this->secret('Webhook signing secret (whsec_…)');

        if (! $secret || ! $webhookSecret) {
            $this->error('Both the API key and the webhook signing secret are required.');

            return self::FAILURE;
        }

        $key = DB::transaction(function () use ($environment, $secret, $webhookSecret) {
            ApiKey::where('environment', $environment)->update(['is_active' => false]);

            return ApiKey::create([
                'name' => $this->option('name') ?: "{$environment} key",
                'environment' => $environment,
                'secret' => $secret,
                'webhook_secret' => $webhookSecret,
                'is_active' => true,
            ]);
        });

        try {
            $clients->make($key)->usage(now('UTC')->toDateString(), now('UTC')->toDateString());
            $this->info("Stored and verified key {$key->hint} for \"{$environment}\". Older keys for this environment were deactivated.");
        } catch (PixelforgeException $e) {
            $this->warn("Stored key {$key->hint}, but Pixelforge said: {$e->errorCode} ({$e->getMessage()}).");
        }

        $this->line('You can now remove PIXELFORGE_API_KEY and PIXELFORGE_WEBHOOK_SECRET from .env.');

        return self::SUCCESS;
    }
}
