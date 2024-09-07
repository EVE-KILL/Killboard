<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Logger\Logger;
use EK\Models\Killmails;
use EK\RabbitMQ\RabbitMQ;
use MongoDB\BSON\UTCDateTime;

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

        $parsedKillmail = $this->cleanupTimestamps($parsedKillmail);

        // Do not emit out mails that are beyond 7 days older than the current time
        $currentTime = time();
        $killmailTime = strtotime($parsedKillmail['kill_time']);
        if ($currentTime - $killmailTime > (60*60*24*7)) {
            return;
        }

        // Add routing keys based on the parsed killmail data
        $systemId = $parsedKillmail['system_id'] ?? null;
        if ($systemId) {
            $routingKeys[] = "system.{$systemId}";
        }

        $regionId = $parsedKillmail['region_id'] ?? null;
        if ($regionId) {
            $routingKeys[] = "region.{$regionId}";
        }

        $shipId = $parsedKillmail['victim']['ship_id'] ?? null;
        if ($shipId) {
            $routingKeys[] = "victim.ship.{$shipId}";
        }

        $shipGroupId = $parsedKillmail['victim']['ship_group_id'] ?? null;
        if ($shipGroupId) {
            $routingKeys[] = "victim.ship_group.{$shipGroupId}";
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

            $shipId = $attacker['ship_id'] ?? null;
            if ($shipId) {
                $routingKeys[] = "attacker.ship.{$shipId}";
            }

            $shipGroupId = $attacker['ship_group_id'] ?? null;
            if ($shipGroupId) {
                $routingKeys[] = "attacker.ship_group.{$shipGroupId}";
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

    protected function cleanupTimestamps(array $data): array
    {
        foreach ($data as $key => $value) {
            // Check if the value is an instance of UTCDateTime
            if ($value instanceof UTCDateTime) {
                $data[$key] = $value->toDateTime()->format('Y-m-d H:i:s');
            }

            // Check if the value is an array
            if (is_array($value)) {
                // If the array has the structure containing $date and $numberLong
                if (isset($value['$date']['$numberLong'])) {
                    $data[$key] = (new UTCDateTime($value['$date']['$numberLong']))->toDateTime()->format('Y-m-d H:i:s');
                } else {
                    // Recursively process nested arrays
                    $data[$key] = $this->cleanupTimestamps($value);
                }
            }
        }

        return $data;
    }
}
