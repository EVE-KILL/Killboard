<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateAlliance extends Jobs
{
    protected string $defaultQueue = "alliance";
    protected string $exchange = 'alliance_exchange';

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
        $updateHistory = $data["update_history"] ?? false;
        if ($allianceId === 0) {
            return;
        }

        $allianceData = $this->esiData->getAllianceInfo($allianceId, $updateHistory);

        $this->updateMeilisearch->enqueue([
            'id' => $allianceId,
            'name' => $allianceData['name'],
            'ticker' => $allianceData['ticker'],
            'type' => 'alliance'
        ]);

        // Emit the just updated alliance to the alliance topic
        $channel = $this->rabbitMQ->getChannel();
        $channel->basic_publish(
            new \PhpAmqpLib\Message\AMQPMessage(json_encode($allianceData), [
                'content_type' => 'application/json',
                'delivery_mode' => 2, // Persistent messages
            ]),
            $this->exchange, // Exchange name
            'updates' // Routing key
        );
    }
}
