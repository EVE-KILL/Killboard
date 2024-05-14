<?php

namespace EK\Config;

class Config
{
    public function get(string $key): mixed
    {
        return $_ENV[$key] ?? null;
    }
}