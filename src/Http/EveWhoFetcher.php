<?php

namespace EK\Http;

use bandwidthThrottle\tokenBucket\BlockingConsumer;
use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\storage\FileStorage;
use bandwidthThrottle\tokenBucket\TokenBucket;
use EK\Cache\Cache;
use EK\Config\Config;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class EveWhoFetcher
{
    protected Client $client;
    protected BlockingConsumer $throttleBucket;

    public function __construct(
        protected Cache $cache,
        protected Config $config
    ) {
        // Rate Limit
        $rateLimit = $this->config->get('evewho/rate-limit', 10);

        // Throttle Bucket
        $this->throttleBucket = $this->generateBucket($rateLimit, 'evewho');

        // User Agent
        $userAgent = $this->config->get('evewho/user-agent', 'EK/1.0');

        $this->client = new Client([
            'headers' => [
                'User-Agent' => $userAgent
            ],

            // Timeout after 30 seconds
            'timeout' => 30
        ]);
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

    public function fetch(
        string $path,
        string $requestMethod = 'GET',
        array $query = [],
        string $body = '',
        array $headers = [],
        array $options = [],
        int $cacheTime = 300
    ): ResponseInterface
    {
        // Sort the query, headers and options
        ksort($query);
        ksort($headers);
        ksort($options);

        // Generate the cachekey
        $cacheKey = $this->cache->generateKey($path, $query, $headers, $options, 'evewho');

        // Check if we have a cached response
        if ($this->cache->exists($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Check the throttle bucket
        $this->throttleBucket->consume(1);

        // Make the request
        $result = $this->client->request($requestMethod, $path, [
            'query' => $query,
            'body' => $body,
            'headers' => $headers,
            'options' => $options
        ]);

        // Cache the response
        $this->cache->set($cacheKey, $result, $cacheTime);

        return $result;
    }
}