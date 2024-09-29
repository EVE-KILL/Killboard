<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\ESI;
use EK\Logger\Logger;
use EK\Models\Proxies;
use EK\RabbitMQ\RabbitMQ;
use MongoDB\BSON\UTCDateTime;

class ValidateProxy extends Jobs
{
    protected string $defaultQueue = 'high';
    protected array $knownData = [
        '/latest/characters/268946627' => [
            "bloodline_id" => 1,
            "corporation_id" => 1000167,
            "gender" => "male",
            "name" => "Karbowiak",
        ]
    ];

    public function __construct(
        protected Proxies $proxies,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
        protected ESI $esiFetcher
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $proxyId = $data['proxy_id'];

        // Get the proxy data
        $proxyData = $this->proxies->findOne(['proxy_id' => $proxyId]);

        // Get the last time it was validated
        $lastValidated = isset($proxyData['lastValidated']) ?
            (new UTCDateTime($proxyData['lastValidated']))->toDateTime()->getTimestamp() : 0;

        // If it was validated less than 30 minutes ago, skip
        if ($lastValidated > time() - 1800) {
            return;
        }

        // For each knownData we need to fetch the data using the proxy
        // And then compare the data gotten from the proxy, against the known data
        // If it all checks out, we can validate the proxy and set it into rotation
        foreach($this->knownData as $testPath => $knownData) {
            $response = $this->esiFetcher->fetch($testPath, proxy_id: $proxyId);
            $body = json_decode($response['body'], true);
            $status = 'inactive';

            foreach($knownData as $key => $value) {
                if ($body[$key] === $value) {
                    $status = 'active';
                } else {
                    $status = 'inactive';
                    break;
                }
            }

            $data = array_merge($proxyData->toArray(), [
                'last_modified' => new UTCDateTime(),
                'last_validated' => new UTCDateTime(),
                'status' => $status
            ]);

            $this->proxies->collection->updateOne(
                ['proxy_id' => $proxyId],
                [
                    '$set' => $data
                ]
            );
        }

    }
}
