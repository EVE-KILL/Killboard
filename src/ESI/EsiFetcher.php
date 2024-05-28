<?php

namespace EK\ESI;

use bandwidthThrottle\tokenBucket\BlockingConsumer;
use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\storage\FileStorage;
use bandwidthThrottle\tokenBucket\TokenBucket;
use EK\Cache\Cache;
use EK\Config\Config;
use EK\Logger\ESILogger;
use EK\Models\Proxies;
use GuzzleHttp\Client;

class EsiFetcher
{
    protected Client $client;
    protected BlockingConsumer $throttleBucket;

    public function __construct(
        protected Cache $cache,
        protected ESILogger $logger,
        protected Proxies $proxies,
        protected Config $config
    ) {
        $throttleRateLimit = $this->config->get('esi/global-rate-limit', 500);
        $this->throttleBucket = $this->generateBucket($throttleRateLimit, 'esi_global');
    }

    protected function generateBucket(int $rateLimit, string $bucketName): BlockingConsumer
    {
        $bucketPath = "/tmp/{$bucketName}_rate_limit.bucket";
        $storage = new FileStorage($bucketPath);
        $rate = new Rate($rateLimit, Rate::SECOND);
        $bucket = new TokenBucket($rateLimit, $rate, $storage);
        $bucket->bootstrap($rateLimit);
        return new BlockingConsumer($bucket);
    }

    public function getClient(array $proxy): Client
    {
        if (!isset($proxy['url'])) {
            throw new \Exception('Proxy URL not set');
        }

        return new Client([
            'base_uri' => $proxy['url']
        ]);
    }

    public function fetch(
        string $path,
        string $requestMethod = 'GET',
        array $query = [],
        string $body = '',
        array $headers = [],
        array $options = [],
        ?string $proxy_id = null
    ): array
    {
        // Sort the query, headers and options
        ksort($query);
        ksort($headers);
        ksort($options);

        // Generate cache key
        $cacheKey = $this->cache->generateKey($path, $query, $headers);

        // Check the cache for the response
        if ($this->cache->exists($cacheKey) && $proxy_id === null) {
            $result = $this->cache->get($cacheKey);

            // Convert the times to GMT
            $expiresTimeGMT = new \DateTime(
                // Get the time the cache expires
                strtotime(time() + $this->cache->getTTL($cacheKey)),
                new \DateTimeZone('GMT')
            );
            $currentTimeGMT = new \DateTime('now', new \DateTimeZone('GMT'));

            return [
                'status' => 304,
                'headers' => array_merge($result['headers'], [
                    'Expires' => $expiresTimeGMT->format('D, d M Y H:i:s T'),
                    'Date' => $currentTimeGMT->format('D, d M Y H:i:s T'),
                    'X-Esi-Error-Limit-Remain' => '100',
                    'X-Esi-Error-Limit-Reset' => '60',
                ]),
                'body' => $result['body'],
            ];
        }

        // Use the proxy_id to get the specific proxy to use, or get a random active proxy
        if ($proxy_id !== null) {
            $proxy = $this->proxies->findOne(['proxy_id' => $proxy_id])->toArray();
        } else {
            $proxy = $this->proxies->getRandomProxy();
            if (empty($proxy)) {
                throw new \RuntimeException('No proxies available');
            }
        }

        // Consume a token from the global rate limit bucket
        $this->throttleBucket->consume(1);

        // Get the client with the proxy we just selected
        $client = $this->getClient($proxy);

        // Execute call to ESI via proxy
        $response = $client->request($requestMethod, $path, [
            'query' => $query,
            'body' => $body,
            'headers' => array_merge($headers, [
                'User-Agent' => $this->config->get('esi/user-agent', 'EK/1.0'),
            ]),
            'options' => $options,
            'http_errors' => false,
        ]);

        // Get the status
        $statusCode = $response->getStatusCode();

        // Get the response content
        $content = $response->getBody()->getContents();

        // Get the expires header from the response (The Expires and Date are in GMT)
        $now = new \DateTime('now', new \DateTimeZone('GMT'));
        $expires = $response->getHeader('Expires')[0] ?? $now->format('D, d M Y H:i:s T');
        $serverTime = $response->getHeader('Date')[0] ?? $now->format('D, d M Y H:i:s T');
        $expiresInSeconds = strtotime($expires) - strtotime($serverTime) ?? 60;

        // Emit a log message
        $this->logger->info(sprintf(
            '%s %s %s %s',
            $requestMethod,
            $path,
            $statusCode,
            round(strlen($content) / 1024, 2) . 'KB',
        ), [
            'proxy_id' => $proxy['proxy_id']
        ]);

        // Handle the various status codes
        if ($statusCode === 401 && str_contains($content, 'You have been banned')) {
            // Ban the proxy
            $this->proxies->collection->updateOne(
                [
                    'proxy_id' => $proxy['proxy_id']
                ], [
                    'status' => 'banned',
                    'last_modified' => new \DateTime(),
                    'last_validated' => new \DateTime()
                ]
            );

            // @TODO add a notification system to alert discord a proxy got banned
        }

        if (!in_array($statusCode, [200, 304])) {
            $this->throttleBucket->consume($this->config->get('esi/global-rate-limit', 500) / 4);
        }

        if ($expiresInSeconds > 0 && in_array($statusCode, [200, 304])) {
            $this->cache->set($cacheKey, [
                'headers' => $response->getHeaders(),
                'body' => $content
            ], $expiresInSeconds);
        }

        return [
            'status' => $statusCode,
            'headers' => $response->getHeaders(),
            'body' => $content,
        ];
    }
}