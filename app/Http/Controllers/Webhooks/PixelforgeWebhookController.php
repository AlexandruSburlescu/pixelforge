<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * POST /webhooks/pixelforge — outside the web middleware (no session, no CSRF).
 *
 * Verify → store once (UNIQUE event_id) → 204 → process in a queued job.
 * Answering fast matters: anything slower than 10s or non-2xx gets redelivered.
 */
class PixelforgeWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 1_048_576;

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent(); // raw body: verify before decoding

        if (strlen($payload) > self::MAX_BODY_BYTES) {
            return response('Payload too large.', 413);
        }

        $key = WebhookSignature::verify(
            (string) $request->header('Pixelforge-Signature'),
            $payload,
            (int) config('pixelforge.webhook_tolerance_seconds'),
        );

        if (! $key) {
            return response('Invalid Pixelforge signature.', 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            return response('Malformed event.', 400);
        }

        $object = (array) data_get($event, 'data.object', []);

        $inserted = DB::table('webhook_events')->insertOrIgnore([
            'event_id' => mb_substr($event['id'], 0, 40),
            'type' => mb_substr($event['type'], 0, 40),
            'provider_generation_id' => isset($object['id']) ? mb_substr((string) $object['id'], 0, 40) : null,
            'delivery_attempt' => min(255, max(1, (int) $request->header('Pixelforge-Delivery-Attempt', 1))),
            'payload' => $payload,
            'event_created_at' => $this->time($event['created_at'] ?? null),
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            // Seen before: count it for the admin view, do nothing else.
            WebhookEvent::where('event_id', $event['id'])->increment('duplicates');

            return response()->noContent();
        }

        ProcessWebhookEvent::dispatch($event['id']);

        return response()->noContent();
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
