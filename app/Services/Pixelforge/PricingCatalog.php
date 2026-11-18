<?php

namespace App\Services\Pixelforge;

use App\Models\ApiKey;
use App\Services\Pricing\PricingTable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
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
        private readonly Http $http,
    ) {}

    public function current(): PricingTable
    {
        $body = $this->cache->get(self::FRESH_KEY);

        if (! is_array($body)) {
            try {
                // GET /v1/pricing needs no key: use the logged client when one exists,
                // a plain request otherwise, so quotes work before a key is set up.
                $body = ApiKey::current()
                    ? $this->clients->make()->pricing()
                    : $this->http->baseUrl((string) config('pixelforge.base_url'))->acceptJson()->timeout(10)->get('/pricing')->throw()->json();
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
