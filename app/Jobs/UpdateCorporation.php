<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\ESIData;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;

class UpdateCorporation extends Jobs
{
    protected string $defaultQueue = "corporation";
    protected string $exchange = 'corporation_exchange';

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
        $updateHistory = $data["update_history"] ?? false;
        if ($corporationId === 0 || $corporationId === null) {
            return;
        }

        $corporationData = $this->esiData->getCorporationInfo($corporationId, $updateHistory);

        $this->updateMeilisearch->enqueue([
            'id' => $corporationId,
            'name' => $corporationData['name'],
            'ticker' => $corporationData['ticker'],
            'type' => 'corporation'
        ]);

        // Emit the just updated corporation to the corporation topic
        $channel = $this->rabbitMQ->getChannel();
        $channel->basic_publish(
            new \PhpAmqpLib\Message\AMQPMessage(json_encode($corporationData), [
                'content_type' => 'application/json',
                'delivery_mode' => 2, // Persistent messages
            ]),
            $this->exchange, // Exchange name
            'updates' // Routing key
        );
    }
}
