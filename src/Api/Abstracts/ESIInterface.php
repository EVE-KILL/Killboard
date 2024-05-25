<?php

namespace EK\Api\Abstracts;

use EK\ESI\EsiFetcher;

abstract class ESIInterface
{
    protected bool $useRemapper = false;

    public function __construct(
        protected EsiFetcher $esiFetcher
    )
    {

    }

    protected function fetch(
        string $path,
        string $requestMethod = 'GET',
        array  $query = [],
        string $body = '',
        array  $headers = [],
        array  $options = []
    ): array
    {
        // Try and get the data twice, if it fails both times, throw an exception
        $attempts = 0;
        $errorMessage = '';

        while ($attempts < 2) {
            try {
                $response = $this->esiFetcher->fetch($path, $requestMethod, $query, $body, $headers, $options);

                if (in_array($response['status'], [200, 304])) {
                    return json_decode($response['body'], true);
                }

                $errorMessage = 'Error getting data from ESI: ' . $response['status'] . ' ' . $response['body'];
                throw new \RuntimeException();
            } catch (\Exception $e) {
                $attempts++;

                // Wait 500ms before trying again
                usleep(500000);
            }
        }

        throw new \Exception($errorMessage);
    }
}
