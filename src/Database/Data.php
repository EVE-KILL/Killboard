<?php

namespace EK\Database;

use League\Container\Container;

class Data
{
    public function __construct(
        protected Container $container
    ) {
    }

    public function mapData(array $data): self
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            } else {
                throw new \RuntimeException('Property ' . $key . ' does not exist in ' . get_class($this));
            }
        }

        return $this;
    }

    public function getModel()
    {
        $className = get_class($this);
        $className = str_replace('Data', 'Models', $className);
        return $this->container->get($className);
    }
}