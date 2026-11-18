<?php

namespace App\Services\Pixelforge;

use App\Services\Pricing\PricingTable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

/**
 * Current Pixelforge rates. The docs say not to hardcode them, so they're fetched
 * from GET /v1/pricing, cached briefly, and the last good copy is kept as a
 * fallback so a pricing outage never blocks quoting.
 */
class PricingCatalog
{
    private const FRESH_KEY = 'pixelforge:pricing:fresh';

    private const LAST_GOOD_KEY = 'pixelforge:pricing:last-good';

    public function __construct(
        private readonly Cache $cache,
        private readonly PixelforgeClientFactory $clients,
    ) {}

    public function current(): PricingTable
    {
        $body = $this->cache->get(self::FRESH_KEY);

        if (! is_array($body)) {
            try {
                $body = $this->clients->make()->pricing();
                $this->cache->put(self::FRESH_KEY, $body, now()->addMinutes(10));
                $this->cache->forever(self::LAST_GOOD_KEY, $body);
            } catch (Throwable $e) {
                $body = $this->cache->get(self::LAST_GOOD_KEY);

                if (! is_array($body)) {
                    throw $e;
                }
            }
        }

        return PricingTable::fromApi($body);
    }
}
