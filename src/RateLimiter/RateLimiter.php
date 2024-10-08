<?php

namespace EK\RateLimiter;

use EK\Redis\Redis;
use Redis as PhpRedis;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimiter
{
    protected PhpRedis $client;

    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $redis->getClient();
    }

    public function createRateLimiter(string $id, string $policy = 'sliding_window', int $limit = 10): LimiterInterface
    {
        $cacheItemPool = new RedisAdapter($this->client);
        $store = new RedisStore($this->client);
        $lock = new LockFactory($store);
        $factory = new RateLimiterFactory([
            'id' => $id,
            'policy' => $policy,
            'limit' => $limit,
            'interval' => '1 second',
        ], new CacheStorage($cacheItemPool), $lock);

        return $factory->create();
    }
}
