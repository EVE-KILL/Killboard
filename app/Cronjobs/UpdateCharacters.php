<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Fetchers\ESI;
use EK\Jobs\UpdateAlliance;
use EK\Jobs\UpdateCharacter;
use EK\Jobs\UpdateCorporation;
use EK\Logger\StdOutLogger;
use EK\Models\Characters;
use MongoDB\BSON\UTCDateTime;

class UpdateCharacters extends Cronjob
{
    protected string $cronTime = "* * * * *";

    public function __construct(
        protected Characters $characters,
        protected UpdateCharacter $updateCharacter,
        protected UpdateCorporation $updateCorporation,
        protected UpdateAlliance $updateAlliance,
        protected StdOutLogger $logger,
        protected ESI $esi
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $characterCount = $this->characters->aproximateCount();

        // Round up the limit to a whole number
        $limit = (int) round($characterCount / (60 * 24), 0);

        // Find 1000 characters that haven't been updated in the last 24 hours
        $characters = $this->characters->find([
            'last_modified' => ['$lt' => new UTCDateTime((time() - 24 * 60 * 60) * 1000)],
            'deleted' => ['$ne' => true],
        ], ['limit' => $limit, 'projection' => ['_id' => 0, 'character_id' => 1, 'corporation_id' => 1, 'alliance_id' => 1]]);

        // Convert the generator to an array
        $characters = iterator_to_array($characters);

        // Split the characters into chunks of 1000
        $chunks = array_chunk($characters, 1000);
        foreach ($chunks as $chunk) {
            $this->processChunk($chunk);
        }
    }

    private function processChunk(array $characters): void
    {
        // Get the affiliations from the affiliate endpoint
        $affiliationResponse = $this->fetchAffiliation($characters);

        // Create a map of character_id to affiliation data for quick lookup
        $affiliationMap = [];
        foreach ($affiliationResponse as $affil) {
            if (isset($affil['character_id'])) {
                $affiliationMap[$affil['character_id']] = $affil;
            }
        }

        $this->logger->info("Processing affiliations for characters.");

        // Find the characters with a different corporation or alliance id in the affiliation array versus the local database
        $updates = [];
        foreach ($characters as $character) {
            $charId = $character['character_id'];

            if (!isset($affiliationMap[$charId])) {
                $this->logger->warning("No affiliation data found for character ID: {$charId}");
                continue;
            }

            $affil = $affiliationMap[$charId];

            if ((isset($affil['corporation_id']) && isset($character['corporation_id'])) && $affil['corporation_id'] !== $character['corporation_id']) {
                $updates[] = [
                    'character_id' => $charId,
                    'corporation_id' => $affil['corporation_id'],
                ];
            }

            if ((isset($affil['alliance_id']) && isset($character['alliance_id'])) && $affil['alliance_id'] !== $character['alliance_id']) {
                $updates[] = [
                    'character_id' => $charId,
                    'alliance_id' => $affil['alliance_id'],
                ];
            }
        }

        if (!empty($updates)) {
            foreach ($updates as $update) {
                $this->updateCharacter->enqueue(['character_id' => $update['character_id'], 'force_update' => true, 'update_history' => true], priority: 10);
                if (isset($update['corporation_id'])) {
                    $this->updateCorporation->enqueue(['corporation_id' => $update['corporation_id'], 'force_update' => true, 'update_history' => true], priority: 10);
                }
                if (isset($update['alliance_id'])) {
                    $this->updateAlliance->enqueue(['alliance_id' => $update['alliance_id'], 'force_update' => true], priority: 10);
                }
            }
            $this->logger->info("Dispatched update jobs for " . count($updates) . " characters.");

            // Update the rest of the characters to the current time
            $currentTime = new UTCDateTime(time() * 1000);
            $characterIdsMinusUpdates = array_diff(array_map(fn ($char) => $char['character_id'], $characters), array_column($updates, 'character_id'));
            $this->characters->collection->updateMany(
                ['character_id' => ['$in' => $characterIdsMinusUpdates]],
                ['$set' => ['last_modified' => $currentTime]]
            );
            $this->logger->info("Updated last_modified for " . count($characterIdsMinusUpdates) . " characters.");
        } else {
            // If there are no updates, update the last_modified field to the current time
            $currentTime = new UTCDateTime(time() * 1000);
            $this->characters->collection->updateMany(
                ['character_id' => ['$in' => array_map(fn ($char) => $char['character_id'], $characters)]],
                ['$set' => ['last_modified' => $currentTime]]
            );
            $this->logger->info("Updated last_modified for " . count($characters) . " characters.");
        }
    }

    private function fetchAffiliation(array $characters, int $attempts = 0): array
    {
        $affiliations = [];
        $maxAttempts = 3;
        $characters = array_map(function($character) {
            return $character['character_id'];
        }, $characters);

        try {
            // Send the characters to the affiliate endpoint
            $url = '/latest/characters/affiliation/?rand=' . uniqid();
            $request = $this->esi->fetch($url, 'POST', [], json_encode($characters), cacheTime: 0);
            $affiliationResponse = json_validate($request['body']) ? json_decode($request['body'], true) : null;
            $affiliations = array_merge($affiliations, $affiliationResponse);
        } catch(\Exception $e) {
            dump("error, splitting in half");
            // If $attempts is === $maxAttempts, we submit all the characters to the regular updateCharacter job
            if ($attempts === $maxAttempts) {
                dump("error, max attempts reached, submitting to regular updateCharacter job");
                $updates = [];
                foreach($characters as $character) {
                    $updates[] = ['character_id' => $character['character_id'], 'force_update' => true, 'update_history' => true];
                }
                $this->updateCharacter->massEnqueue($updates);
                return [];
            }

            // Request failed, split the characters in half and try again
            $halves = array_chunk($characters, 500);

            foreach ($halves as $half) {
                $affiliationRepsonse = $this->fetchAffiliation($half, $attempts + 1);
            }

            $affiliations = array_merge($affiliations, $affiliationRepsonse);
        }

        return $affiliations;
    }
}
