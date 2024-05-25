<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\ESI\EsiFetcher;
use EK\Models\KillmailsESI;
use EK\Models\Proxies;
use EK\Redis\Redis;
use MongoDB\BSON\UTCDateTime;

class validateProxy extends Jobs
{
    protected array $knownData = [
        '/latest/characters/268946627' => [
            "birthday" => "2005-04-12T18:22:00Z",
            "bloodline_id" => 1,
            "corporation_id" => 1000167,
            "gender" => "male",
            "name" => "Karbowiak",
        ]
    ];

    public function __construct(
        protected Proxies $proxies,
        protected Redis $redis,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $proxyID = $data['proxyID'];

        // Get the proxy data
        $proxyData = $this->proxies->findOne(['proxyID' => $proxyID]);

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
            $response = $this->esiFetcher->fetch($testPath, proxyId: $proxyID);
            $status = 'inactive';

            foreach($knownData as $key => $value) {
                if ($response[$key] === $value) {
                    $status = 'active';
                } else {
                    $status = 'inactive';
                    break;
                }
            }

            $data = array_merge($proxyData->toArray(), [
                'last_modified' => new UTCDateTime(),
                'last_validated' => new UTCDateTime(),
                'status' => 'active'
            ]);

            $this->proxies->collection->updateOne(
                ['proxyID' => $proxyID],
                [
                    '$set' => $data
                ]
            );
        }

    }
}