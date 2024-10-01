<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateAlliance extends Jobs
{
    protected string $defaultQueue = "alliance";

    public function __construct(
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
        protected UpdateMeilisearch $updateMeilisearch,
        protected ESIData $esiData
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $allianceId = $data["alliance_id"];
        $forceUpdate = $data["force_update"] ?? false;
        if ($allianceId === 0) {
            return;
        }

        $allianceData = $this->esiData->getAllianceInfo($allianceId, $forceUpdate);

        $this->updateMeilisearch->enqueue([
            'id' => $allianceId,
            'name' => $allianceData['name'],
            'ticker' => $allianceData['ticker'],
            'type' => 'alliance'
        ]);
    }
}
