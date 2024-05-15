<?php

namespace EK\Config;

class Config
{
    protected $options = [];
    public function __construct() {
        $this->options = require_once BASE_DIR . '/config/config.php';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('/', $key);
        $result = $this->options;

        foreach($keys as $key) {
            if (isset($result[$key])) {
                $result = $result[$key];
            } else {
                return $default;
            }
        }

        return $result;
    }
}