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
    protected bool $useProxy = true;

    public function __construct(
        protected Cache $cache,
        protected Proxies $proxies,
        protected RateLimiter $rateLimiter,
        protected Webhooks $webhooks,
        protected Logger $logger
    ) {
        parent::__construct($cache, $proxies, $rateLimiter, $logger);
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
        $expiresInSeconds = abs($expiresInSeconds);

        // Retrieve error limit remaining and reset time from headers
        $esiErrorLimitRemaining = (int) ($response->getHeader('X-Esi-Error-Limit-Remain')[0] ?? 100);
        $esiErrorLimitReset = (int) ($response->getHeader('X-Esi-Error-Limit-Reset')[0] ?? 0);

        // Cache the values
        $this->cache->set('esi_error_limit_remaining', $esiErrorLimitRemaining);
        $this->cache->set('esi_error_limit_reset', $esiErrorLimitReset);

        // Calculate progressive usleep time (in microseconds) based on inverse of error limit remaining
        if ($esiErrorLimitRemaining < 100) {
            // Error limit remaining should inversely affect the sleep time
            // The closer it is to zero, the longer the sleep
            $maxSleepTimeInMicroseconds = $esiErrorLimitReset * 1000000; // max sleep time, e.g., reset in seconds converted to microseconds

            // Calculate the inverse factor (higher remaining errors = lower sleep)
            $inverseFactor = (100 - $esiErrorLimitRemaining) / 100;

            // Exponentially scale the sleep time as remaining errors approach zero
            $sleepTimeInMicroseconds = (int) ($inverseFactor * $inverseFactor * $maxSleepTimeInMicroseconds);

            // Ensure sleep time is not too short, minimum of 1 millisecond (1000 microseconds)
            $sleepTimeInMicroseconds = max(1000, $sleepTimeInMicroseconds);

            // Apply usleep (sleep in microseconds)
            usleep($sleepTimeInMicroseconds);
        }

        // Handle 420 error code if needed
        if ($statusCode === 420) {
            $sleepTime = $expiresInSeconds === 0 ? 60 : $expiresInSeconds;

            \Sentry\captureMessage('420 Error, sleeping for ' . $sleepTime . ' seconds: ' . $content);

            $this->cache->set('fetcher_paused', $sleepTime, $sleepTime);
            sleep($sleepTime);
        }

        return $response;
    }

}
