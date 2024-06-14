<?php

namespace EK\Fetchers;

use bandwidthThrottle\tokenBucket\BlockingConsumer;
use EK\Cache\Cache;
use EK\Http\Fetcher;
use EK\Logger\FileLogger;
use EK\Models\Proxies;
use EK\Webhooks\Webhooks;
use Psr\Http\Message\ResponseInterface;

class ESI extends Fetcher
{
    protected string $baseUri = 'https://esi.evetech.net/latest/';
    protected string $userAgent = 'EK/1.0';
    protected string $bucketName = 'esi_global';
    protected bool $useProxy = false;
    protected bool $useThrottle = true;
    protected int $bucketLimit = 50;
    protected int $timeout = 30;
    protected BlockingConsumer $throttleBucket;
    protected FileLogger $logger;

    public function __construct(
        protected Cache $cache,
        protected Proxies $proxies,
        protected Webhooks $webhooks
    ) {
        parent::__construct($cache, $proxies);
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
            sleep($expiresInSeconds);
        }

        if ($statusCode >= 500 && $statusCode <= 600) {
            $this->webhooks->sendToEsiErrors($statusCode . ' Error: ' . $content);
        }

        return $response;
    }
}