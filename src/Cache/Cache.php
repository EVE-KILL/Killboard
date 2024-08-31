<?php
namespace EK\Cache;

use EK\Redis\Redis;
use Redis as PhpRedis;

class Cache
{
    protected PhpRedis $client;

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
        $value = $this->client->get($key);
        if ($value === null) {
            return null;
        }

        // Decode the JSON and check for errors
        $decodedValue = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null; // Invalid JSON
        }

        return $decodedValue;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        // Encode the value as JSON
        $encodedValue = json_encode($value);

        // Check for JSON encoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Unable to encode value as JSON');
        }

        if ($ttl > 0) {
            // Set the value with a TTL (in seconds)
            $this->client->setex($key, $ttl, $encodedValue);
        } else {
            // Set the value without a TTL
            $this->client->set($key, $encodedValue);
        }
    }

    public function remove(string $key): void
    {
        // Remove the key
        $this->client->del($key);
    }

    public function exists(string $key): bool
    {
        // Check if the key exists
        return $this->client->exists($key) > 0;
    }
}
