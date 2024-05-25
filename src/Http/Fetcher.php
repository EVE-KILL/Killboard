<?php

namespace EK\Http;

use EK\Config\Config;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class Fetcher
{
    protected Client $client;

    public function __construct(
        protected Config $config
    ) {
        // User Agent
        $userAgent = $this->config->get('fetcher/user-agent', 'EK/1.0');

        $this->client = new Client([
            'headers' => [
                'User-Agent' => $userAgent
            ],

            // Timeout after 10 seconds
            'timeout' => 10
        ]);
    }

    public function fetch(
        string $path,
        string $requestMethod = 'GET',
        array $query = [],
        string $body = '',
        array $headers = [],
        array $options = []
    ): ResponseInterface
    {
        $response = $this->client->request($requestMethod, $path, [
            'query' => $query,
            'body' => $body,
            'headers' => $headers,
            'options' => $options
        ]);

        return $response;
    }
}