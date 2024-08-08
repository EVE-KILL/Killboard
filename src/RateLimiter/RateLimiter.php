<?php

namespace EK\RateLimiter;

use EK\Redis\Redis;
use Predis\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimiter
{
    protected Client $client;

    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $redis->getClient();
    }

    public function createRateLimiter(string $id, string $policy = 'token_bucket', int $limit = 10, array $rate = ['interval' => '1 minute']): LimiterInterface
    {
        $cacheItemPool = new RedisAdapter($this->client);
        $factory = new RateLimiterFactory([
            'id' => $id,
            'policy' => $policy,
            'limit' => $limit,
            'rate' => $rate,
        ], new CacheStorage($cacheItemPool));

        return $factory->create();
    }
}
