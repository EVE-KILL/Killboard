<?php

namespace EK\Fetchers;

use EK\Cache\Cache;
use EK\Http\Fetcher;
use EK\Models\Proxies;
use EK\RateLimiter\RateLimiter;
use EK\Webhooks\Webhooks;
use Psr\Http\Message\ResponseInterface;

class ESI extends Fetcher
{
    protected string $baseUri = 'https://esi.evetech.net/latest/';
    protected string $bucketName = 'esi_global';
    protected bool $useThrottle = true;
    protected int $bucketLimit = 50;

    public function __construct(
        protected Cache $cache,
        protected Proxies $proxies,
        protected RateLimiter $rateLimiter,
        protected Webhooks $webhooks
    ) {
        parent::__construct($cache, $proxies, $rateLimiter);
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
                $this->webhooks->sendToEsiErrors('420 Error, sleeping for ' . $sleepTime . ' seconds: ' . $content);
                // Consume all tokens to halt all the workers
                $this->throttleBucket->consume($this->bucketLimit);
                // Sleep for the time it takes for the error rate to expire
                sleep($sleepTime);
                break;
        }

        if ($statusCode >= 400 && $statusCode <= 500) {
            $this->webhooks->sendToEsiErrors($statusCode . ' Error: ' . $content);
            sleep($expiresInSeconds > 0 ? $expiresInSeconds : 1);
        }

        if ($statusCode >= 500 && $statusCode <= 600) {
            $this->webhooks->sendToEsiErrors($statusCode . ' Error: ' . $content);
        }

        return $response;
    }
}
