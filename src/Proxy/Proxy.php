<?php

namespace EK\Proxy;

class Proxy
{
    public function __construct(

    ) {
    }

    public function getRandom(): string
    {
        return '';
    }

    public function getProxy(string $id): string
    {
        return '';
    }

    public function addProxy(string $name, string $externalAddress, string $listenIP, string $port): bool
    {
        return false;
    }

    public function testProxy(string $id): bool
    {
        return false;
    }

    public function removeProxy(string $id): bool
    {
        return false;
    }
}