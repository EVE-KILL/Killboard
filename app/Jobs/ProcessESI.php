<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\ESI;
use EK\Logger\Logger;
use EK\Models\Killmails;
use EK\RabbitMQ\RabbitMQ;

class ProcessESI extends Jobs
{
    protected string $defaultQueue = 'esi';

    public function __construct(
        protected ProcessKillmail $processKillmail,
        protected Killmails $killmails,
        protected ESI $esi,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $accessToken = $data['access_token'];
        $characterId = $data['character_id'];
        $corporationId = $data['corporation_id'];
        $fetchCorporation = $data['fetch_corporation'];

        $killmails = $this->fetchCharacterKillmails($characterId, $accessToken);

        if ($fetchCorporation) {
            $corporationKillmails = $this->fetchCorporationKillmails($corporationId, $accessToken);
            $killmails = array_merge($killmails, $corporationKillmails);
        }

        if (!empty($killmails)) {
            foreach ($killmails as $killmail) {
                // Don't process if we have already processed it
                $existingKillmail = $this->killmails->findOne([
                    'killmail_id' => $killmail['killmail_id'],
                    'attackers' => ['$exists' => true]
                ], [
                    'projection' => ['_id' => 1]
                ]);

                if (!empty($existingKillmail)) {
                    continue;
                }

                $this->processKillmail->enqueue([
                    'killmail_id' => $killmail['killmail_id'],
                    'hash' => $killmail['killmail_hash'],
                ]);
            }
        }
    }

    protected function fetchCorporationKillmails(int $corporationId, string $accessToken, int $page = 1)
    {
        // If we get 1000 killmails back, we need to paginate until we get under 1000
        $characterKillmails = $this->esi->fetch(
            "/latest/corporations/{$corporationId}/killmails/recent/?page={$page}",
            'GET',
            headers: [
                'Authorization' => "Bearer {$accessToken}"
            ]
        );

        $status = $characterKillmails['status'];
        $body = $characterKillmails['body'];
        $killmails = [];

        if ($status === 200) {
            $killmails = json_decode($body, true);

            if (count($killmails) === 1000) {
                $killmails = array_merge($killmails, $this->fetchCharacterKillmails($corporationId, $accessToken, $page + 1));
            }
        }

        return $killmails;
    }

    protected function fetchCharacterKillmails(int $characterId, string $accessToken, int $page = 1)
    {
        // If we get 1000 killmails back, we need to paginate until we get under 1000
        $characterKillmails = $this->esi->fetch(
            "/latest/characters/{$characterId}/killmails/recent/?page={$page}",
            'GET',
            headers: [
                'Authorization' => "Bearer {$accessToken}"
            ]
        );

        $status = $characterKillmails['status'];
        $body = $characterKillmails['body'];
        $killmails = [];

        if ($status === 200) {
            $killmails = json_decode($body, true);

            if (count($killmails) === 1000) {
                $killmails = array_merge($killmails, $this->fetchCharacterKillmails($characterId, $accessToken, $page + 1));
            }
        }

        return $killmails;
    }
}
