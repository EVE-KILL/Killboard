<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Logger\Logger;
use EK\Models\Killmails;
use EK\RabbitMQ\RabbitMQ;

class ProcessKillmail extends Jobs
{
    protected string $defaultQueue = 'killmail';
    protected string $exchange = 'killmail_topic_exchange'; // Set a default exchange for topics
    public bool $requeue = false;

    public function __construct(
        protected Killmails $killmails,
        protected \EK\Helpers\Killmails $killmailHelper,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $killmail_id = $data['killmail_id'];
        $hash = $data['hash'];
        $war_id = $data['war_id'] ?? 0;
        $priority = $data['priority'] ?? 0;

        if (in_array($hash, ['CCP VERIFIED'])) {
            return;
        }

        // Parse the killmail
        $parsedKillmail = $this->killmailHelper->parseKillmail($killmail_id, $hash, $war_id);

        // Insert the parsed killmail into the killmails collection
        $this->killmails->setData($parsedKillmail);
        $this->killmails->save();

        // Load the killmail from the collection
        $loadedKillmail = $this->killmails->find(['killmail_id' => $killmail_id], showHidden: true);
        if ($loadedKillmail->get('emitted') === true) {
            return;
        }

        // Update the emitted field to ensure we don't emit the killmail again
        $this->killmails->collection->updateOne(
            ['killmail_id' => $killmail_id],
            ['$set' => ['emitted' => true]]
        );

        // Emit the killmail to various topics based on the parsed data
        $this->emitToTopics($parsedKillmail);
    }

    protected function emitToTopics(array $parsedKillmail): void
    {
        $routingKeys = [];

        // Add routing keys based on the parsed killmail data
        $systemId = $parsedKillmail['system_id'] ?? null;
        if ($systemId) {
            $routingKeys[] = "system.{$systemId}";
        }

        $regionId = $parsedKillmail['region_id'] ?? null;
        if ($regionId) {
            $routingKeys[] = "region.{$regionId}";
        }

        $characterId = $parsedKillmail['victim']['character_id'] ?? null;
        if ($characterId) {
            $routingKeys[] = "character.{$characterId}";
        }

        $corporationId = $parsedKillmail['victim']['corporation_id'] ?? null;
        if ($corporationId) {
            $routingKeys[] = "corporation.{$corporationId}";
        }

        $allianceId = $parsedKillmail['victim']['alliance_id'] ?? null;
        if ($allianceId) {
            $routingKeys[] = "alliance.{$allianceId}";
        }

        $factionId = $parsedKillmail['victim']['faction_id'] ?? null;
        if ($factionId) {
            $routingKeys[] = "faction.{$factionId}";
        }

        foreach ($parsedKillmail['attackers'] as $attacker) {
            $characterId = $attacker['character_id'] ?? null;
            if ($characterId) {
                $routingKeys[] = "character.{$characterId}";
            }

            $corporationId = $attacker['corporation_id'] ?? null;
            if ($corporationId) {
                $routingKeys[] = "corporation.{$corporationId}";
            }

            $allianceId = $attacker['alliance_id'] ?? null;
            if ($allianceId) {
                $routingKeys[] = "alliance.{$allianceId}";
            }

            $factionId = $attacker['faction_id'] ?? null;
            if ($factionId) {
                $routingKeys[] = "faction.{$factionId}";
            }
        }

        // Add the killmail to the all routing key as well
        $routingKeys[] = 'all';

        // Get the RabbitMQ channel
        $channel = $this->rabbitMQ->getChannel();

        // Publish the message to each routing key
        foreach ($routingKeys as $routingKey) {
            $channel->basic_publish(
                new \PhpAmqpLib\Message\AMQPMessage(json_encode($parsedKillmail), [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2, // Persistent messages
                ]),
                $this->exchange, // Exchange name
                $routingKey // Routing key
            );
        }
    }
}
