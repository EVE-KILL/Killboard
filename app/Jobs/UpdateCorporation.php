<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateCorporation extends Jobs
{
    protected string $defaultQueue = "corporation";

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
        $corporationId = $data["corporation_id"];
        $forceUpdate = $data["force_update"] ?? false;
        $updateHistory = $data["update_history"] ?? false;
        if ($corporationId === 0) {
            return;
        }

        $corporationData = $this->esiData->getCorporationInfo($corporationId, $forceUpdate, $updateHistory);

        $this->updateMeilisearch->enqueue([
            'id' => $corporationId,
            'name' => $corporationData['name'],
            'ticker' => $corporationData['ticker'],
            'type' => 'corporation'
        ]);
    }
}
