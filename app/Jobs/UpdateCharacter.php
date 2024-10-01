<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateCharacter extends Jobs
{
    protected string $defaultQueue = "character";
    public bool $requeue = false;

    public function __construct(
        protected Logger $logger,
        protected RabbitMQ $rabbitMQ,
        protected UpdateMeilisearch $updateMeilisearch,
        protected ESIData $esiData
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $characterId = $data["character_id"];
        $forceUpdate = $data["force_update"] ?? false;
        $updateHistory = $data["update_history"] ?? false;
        if ($characterId === 0) {
            return;
        }

        $characterData = $this->esiData->getCharacterInfo($characterId, $forceUpdate, $updateHistory);

        $this->updateMeilisearch->enqueue([
            'id' => $characterId,
            'name' => $characterData['name'],
            'type' => 'character'
        ]);

    }
}
