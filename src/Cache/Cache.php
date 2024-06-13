<?php

namespace EK\Cache;

use EK\Config\Config;
use Predis\Client;

class Cache
{
    protected \Redis $redis;

    public function __construct(
        protected Config $config
    ) {
        $redis = new \Redis();
        $redis->pconnect(
            $config->get('redis/host'),
            $config->get('redis/port'),
            10,
            'cache'
        );

        $this->redis = $redis;
    }

    public function generateKey(...$args): string
    {
        // Generate a unique key based on the arguments
        return md5(serialize($args));
    }

    public function getTTL(string $key): int
    {
        return $this->redis->ttl($key);
    }

    public function get(string $key): mixed
    {
        $result = $this->redis->get($key);
        if ($result === null) {
            return null;
        }

        return json_decode($result, true);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, json_encode($value));
        } else {
            $this->redis->set($key, json_encode($value));
        }
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key);
    }
}