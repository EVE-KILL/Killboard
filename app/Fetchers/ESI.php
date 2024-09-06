<?php

namespace EK\Fetchers;

use EK\Cache\Cache;
use EK\Http\Fetcher;
use EK\Logger\Logger;
use EK\Models\Proxies;
use EK\RateLimiter\RateLimiter;
use EK\Webhooks\Webhooks;
use Psr\Http\Message\ResponseInterface;

class ESI extends Fetcher
{
    protected string $baseUri = 'https://esi.evetech.net/latest/';
    protected string $bucketName = 'esi_global';
    protected int $rateLimit = 1000;

    public function __construct(
        protected Cache $cache,
        protected RateLimiter $rateLimiter,
        protected Webhooks $webhooks,
        protected Logger $logger
    ) {
        parent::__construct($cache, $rateLimiter, $logger);
    }

    public function handle(ResponseInterface $response): ResponseInterface
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getBody()->getContents();

        // Get the expires header from the response (The Expires and Date are in GMT)
        $now = new \DateTime('now', new \DateTimeZone('GMT'));
        $expires = $response->getHeader('Expires')[0] ?? $now->format('D, d M Y H:i:s T');
        $serverTime = $response->getHeader('Date')[0] ?? $now->format('D, d M Y H:i:s T');
        $expiresInSeconds = (int) strtotime($expires) - strtotime($serverTime) ?? 60;

        switch ($statusCode) {
            case 420:
                $sleepTime = $expiresInSeconds === 0 ? 60 : $expiresInSeconds;
                //$this->webhooks->sendToEsiErrors('420 Error, sleeping for ' . $sleepTime . ' seconds: ' . $content);

                // Tell the other workers to sleep
                $this->cache->set('fetcher_paused', $sleepTime, $sleepTime);

                sleep($sleepTime > 0 ? $sleepTime : 1);
                break;
        }

        return $response;
    }
}
