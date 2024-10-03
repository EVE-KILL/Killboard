<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class EntityHistoryUpdate extends Jobs
{
    protected string $defaultQueue = 'history';
    public bool $requeue = true;

    public function __construct(
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
        protected ESIData $esiData,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $entityId = $data['entity_id'];
        $entityType = $data['entity_type'];

        switch ($entityType) {
            case 'character':
                $this->esiData->getCharacterInfo($entityId, updateHistory: true);
                break;
            case 'corporation':
                $this->esiData->getCorporationInfo($entityId, updateHistory: true);
                break;
        }
    }
}
