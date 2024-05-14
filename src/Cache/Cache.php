<?php

namespace EK\Cache;

class Cache
{
    public function get(string $key): mixed
    {
        $value = apcu_fetch($key, $success);
        if ($success) {
            return $value;
        }

        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return apcu_store($key, $value, $ttl);
    }

    public function exists(string $key): bool
    {
        return apcu_exists($key);
    }
}