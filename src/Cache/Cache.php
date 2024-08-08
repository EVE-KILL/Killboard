<?php
namespace EK\Cache;

class Cache
{
    public function generateKey(...$args): string
    {
        // Generate a unique key based on the arguments
        return md5(serialize($args));
    }

    public function getTTL(string $key): int
    {
        $ttl = \apcu_fetch($key . "_ttl");
        return $ttl !== false ? $ttl - time() : -1;
    }

    public function get(string $key): mixed
    {
        $result = \apcu_fetch($key);
        if ($result === false) {
            return null;
        }

        return json_decode($result, true);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $encodedValue = json_encode($value);
        \apcu_store($key, $encodedValue, $ttl);

        if ($ttl > 0) {
            // Store the expiration time if TTL is set
            \apcu_store($key . "_ttl", time() + $ttl, $ttl);
        }
    }

    public function exists(string $key): bool
    {
        return \apcu_exists($key);
    }
}
