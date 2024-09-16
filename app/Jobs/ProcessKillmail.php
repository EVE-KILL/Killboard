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
        $loadedKillmail = $this->killmails->findOne(['killmail_id' => $killmail_id], showHidden: true);
        if ($loadedKillmail['emitted'] === true) {
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

        $totalValue = $parsedKillmail['total_value'] ?? 0;
        if ($totalValue > 1000000000) {
            $routingKeys[] = '10b';
        }

        if ($totalValue > 500000000) {
            $routingKeys[] = '5b';
        }

        // Abyssal
        if ($parsedKillmail['region_id'] >= 12000000 && $parsedKillmail['region_id'] <= 13000000) {
            $routingKeys[] = 'abyssal';
        }

        // W-Space
        if ($parsedKillmail['region_id'] >= 11000001 && $parsedKillmail['region_id'] <= 11000033) {
            $routingKeys[] = 'wspace';
        }

        // High-sec
        if ($parsedKillmail['system_security'] >= 0.45) {
            $routingKeys[] = 'highsec';
        }

        // Low-sec
        if ($parsedKillmail['system_security'] >= 0.0 && $parsedKillmail['system_security'] < 0.45) {
            $routingKeys[] = 'lowsec';
        }

        // Null-sec
        if ($parsedKillmail['system_security'] < 0.0) {
            $routingKeys[] = 'nullsec';
        }

        // Big kills
        if (in_array($parsedKillmail['victim']['ship_group_id'], [547, 485, 513, 902, 941, 30, 659])) {
            $routingKeys[] = 'bigkills';
        }

        // Solo
        if (count($parsedKillmail['is_solo']) === true) {
            $routingKeys[] = 'solo';
        }

        // NPC
        if (count($parsedKillmail['is_npc']) === true) {
            $routingKeys[] = 'npc';
        }

        // Citadel
        if (in_array($parsedKillmail['victim']['ship_group_id'], [1657, 1406, 1404, 1408, 2017, 2016])) {
            $routingKeys[] = 'citadel';
        }

        // T1
        if (in_array($parsedKillmail['victim']['ship_group_id'], [419, 27, 29, 547, 26, 420, 25, 28, 941, 463, 237, 31])) {
            $routingKeys[] = 't1';
        }

        // T2
        if (in_array($parsedKillmail['victim']['ship_group_id'], [324, 898, 906, 540, 830, 893, 543, 541, 833, 358, 894, 831, 902, 832, 900, 834, 380])) {
            $routingKeys[] = 't2';
        }

        // T3
        if (in_array($parsedKillmail['victim']['ship_group_id'], [963, 1305])) {
            $routingKeys[] = 't3';
        }

        // Frigates
        if (in_array($parsedKillmail['victim']['ship_group_id'], [324, 893, 25, 831, 237])) {
            $routingKeys[] = 'frigates';
        }

        // Destroyers
        if (in_array($parsedKillmail['victim']['ship_group_id'], [420, 541])) {
            $routingKeys[] = 'destroyers';
        }

        // Cruisers
        if (in_array($parsedKillmail['victim']['ship_group_id'], [906, 26, 833, 358, 894, 832, 963])) {
            $routingKeys[] = 'cruisers';
        }

        // Battlecruisers
        if (in_array($parsedKillmail['victim']['ship_group_id'], [419, 540])) {
            $routingKeys[] = 'battlecruisers';
        }

        // Battleships
        if (in_array($parsedKillmail['victim']['ship_group_id'], [27, 898, 900])) {
            $routingKeys[] = 'battleships';
        }

        // Capitals
        if (in_array($parsedKillmail['victim']['ship_group_id'], [547, 485])) {
            $routingKeys[] = 'capitals';
        }

        // Freighters
        if (in_array($parsedKillmail['victim']['ship_group_id'], [513, 902])) {
            $routingKeys[] = 'freighters';
        }

        // Super Carriers
        if (in_array($parsedKillmail['victim']['ship_group_id'], [659])) {
            $routingKeys[] = 'supercarriers';
        }

        // Titans
        if (in_array($parsedKillmail['victim']['ship_group_id'], [30])) {
            $routingKeys[] = 'titans';
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
            $routingKeys[] = "ship.{$shipId}";
        }

        $shipGroupId = $parsedKillmail['victim']['ship_group_id'] ?? null;
        if ($shipGroupId) {
            $routingKeys[] = "ship_group.{$shipGroupId}";
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
                $routingKeys[] = "ship.{$shipId}";
            }

            $shipGroupId = $attacker['ship_group_id'] ?? null;
            if ($shipGroupId) {
                $routingKeys[] = "ship_group.{$shipGroupId}";
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
                $data[$key] = $value->toDateTime()->getTimestamp();
            }

            // Check if the value is an array
            if (is_array($value)) {
                // If the array has the structure containing $date and $numberLong
                if (isset($value['$date']['$numberLong'])) {
                    $data[$key] = (new UTCDateTime($value['$date']['$numberLong']))->toDateTime()->getTimestamp();
                } else {
                    // Recursively process nested arrays
                    $data[$key] = $this->cleanupTimestamps($value);
                }
            }
        }

        return $data;
    }
}
