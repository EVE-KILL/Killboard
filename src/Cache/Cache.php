<?php
namespace EK\Cache;

use EK\Redis\Redis;
use Predis\Client;

class Cache
{
    protected Client $client;
    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $this->redis->getClient();
    }

    public function generateKey(...$args): string
    {
        // Generate a unique key based on the arguments
        return md5(serialize($args));
    }

    public function getTTL(string $key): int
    {
        // Get the TTL of the key
        return $this->client->ttl($key);
    }

    public function get(string $key): mixed
    {
        // Get the value of the key
        return $this->client->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        // Set the value of the key
        $this->client->set($key, $value, $ttl);
    }

    public function exists(string $key): bool
    {
        // Check if the key exists
        return $this->client->exists($key);
    }
}
