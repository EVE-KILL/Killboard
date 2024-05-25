<?php

namespace EK\Cache;

use EK\Config\Config;
use Predis\Client;

class Cache
{
    protected Client $redis;

    public function __construct(
        protected Config $config
    ) {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host' => $config->get('redis/host'),
            'port' => $config->get('redis/port'),
            'password' => $config->get('redis/password'),
            'database' => $config->get('redis/database'),
        ], [
            'prefix' => '',
            'persistent' => true,
            'timeout' => 10,
            'read_write_timeout' => 5,
            'tcp_keepalive' => 1,
            'tcp_nodelay' => true,
            'throw_errors' => false,
        ]);
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