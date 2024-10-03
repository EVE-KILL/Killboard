<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateCharacter extends Jobs
{
    protected string $defaultQueue = "character";
    protected string $exchange = 'character_exchange';

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

        // Emit the just updated character to the character topic
        $channel = $this->rabbitMQ->getChannel();
        $channel->basic_publish(
            new \PhpAmqpLib\Message\AMQPMessage(json_encode($characterData), [
                'content_type' => 'application/json',
                'delivery_mode' => 2, // Persistent messages
            ]),
            $this->exchange, // Exchange name
            'character' // Routing key
        );
    }
}
