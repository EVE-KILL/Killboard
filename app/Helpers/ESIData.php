<?php

namespace EK\Helpers;

use EK\ESI\Alliances as ESIAlliances;
use EK\ESI\Characters as ESICharacters;
use EK\ESI\Corporations as ESICorporations;
use EK\Models\Alliances;
use EK\Models\Celestials;
use EK\Models\Characters;
use EK\Models\Corporations;
use EK\Models\Factions;
use MongoDB\BSON\UTCDateTime;

class ESIData
{
    // Add tracking properties
    protected array $processingCharacters = [];
    protected array $processingCorporations = [];
    protected array $processingAlliances = [];

    // Partial data caches
    protected array $characterNames = [];
    protected array $corporationNames = [];
    protected array $allianceNames = [];

    public function __construct(
        protected ESICharacters $esiCharacters,
        protected ESICorporations $esiCorporations,
        protected ESIAlliances $esiAlliances,
        protected Characters $characters,
        protected Corporations $corporations,
        protected Alliances $alliances,
        protected Factions $factions,
        protected Celestials $celestials
    ) {

    }

    public function getCharacterInfo(int $characterId, bool $forceUpdate = false, bool $updateHistory = false): array
    {
        // Check if character is already being processed
        if (in_array($characterId, $this->processingCharacters, true)) {
            // Return minimal data to prevent loop
            return [
                'character_id' => $characterId,
                'name' => $this->characterNames[$characterId] ?? 'Unknown',
            ];
        }

        // Add character to processing list
        $this->processingCharacters[] = $characterId;

        try {
            $query = [
                'character_id' => $characterId,
                'last_modified' => ['$gte' => new UTCDateTime(time() - (((30 * 24) * 60) * 60) * 1000)],
            ];

            if ($forceUpdate) {
                $characterData = $this->esiCharacters->getCharacterInfo($characterId, cacheTime: 360000);
            } else {
                $characterData = $this->characters->findOneOrNull($query, ['projection' => ['error' => 0]]) ?? $this->esiCharacters->getCharacterInfo($characterId, cacheTime: 360000);
            }
            $error = isset($characterData['error']) ? $characterData['error'] : null;

            if ($error) {
                switch ($error) {
                    case 'Character has been deleted!':
                        $existingData = $this->characters->findOneOrNull([
                            'character_id' => $characterId,
                        ]);

                        if ($existingData) {
                            $deletedCharacter = [
                                'character_id' => $characterId,
                                'name' => $existingData['name'] ?? 'Unknown',
                                'description' => $existingData['description'] ?? '',
                                'birthday' => new UTCDateTime($this->handleDate($existingData['birthday'] ?? '2003-01-01 00:00:00') * 1000),
                                'gender' => $existingData['gender'] ?? '',
                                'race_id' => $existingData['race_id'] ?? 0,
                                'security_status' => (float) number_format($existingData['security_status'], 2),
                                'bloodline_id' => $existingData['bloodline_id'] ?? 0,
                                'corporation_id' => $existingData['corporation_id'] ?? 0,
                                'corporation_name' => $existingData['corporation_name'] ?? 'Unknown',
                                'alliance_id' => $existingData['alliance_id'] ?? 0,
                                'alliance_name' => $existingData['alliance_name'] ?? '',
                                'faction_id' => $existingData['faction_id'] ?? 0,
                                'faction_name' => $existingData['faction_name'] ?? '',
                                'last_modified' => new UTCDateTime(),
                            ];

                            if ($existingData['history']) {
                                $deletedCharacter['history'] = $existingData['history'];
                            }

                            $this->characters->collection->replaceOne([
                                'character_id' => $characterId,
                            ], $deletedCharacter, ['upsert' => true]);

                            return $deletedCharacter;
                        }

                        return [
                            'character_id' => $characterId,
                            'name' => 'Unknown',
                            'corporation_id' => 1000001,
                            'corporation_name' => 'Doomheim',
                            'alliance_id' => 0,
                            'faction_id' => 0,
                            'deleted' => true
                        ];
                        break;
                    default:
                        throw new \Exception($error);
                        break;
                }
            }
            // Store the character name early to assist in recursive calls
            $this->characterNames[$characterId] = $characterData['name'] ?? 'Unknown';

            $corporationData = [];
            $factionData = [];
            $allianceData = [];

            $corporationId = $characterData['corporation_id'];
            if ($corporationId !== 0) {
                $corporationData = $this->getCorporationInfo($corporationId);
            }

            $factionId = $characterData['faction_id'] ?? 0;
            if ($factionId !== 0) {
                $factionData = $this->getFactionInfo($factionId);
            }
            $allianceId = $characterData['alliance_id'] ?? 0;
            if ($allianceId !== 0) {
                $allianceData = $this->getAllianceInfo($allianceId);
            }

            $characterInfo = [
                'character_id' => $characterData['character_id'],
                'name' => $characterData['name'],
                'description' => $characterData['description'] ?? '',
                'birthday' => new UTCDateTime($this->handleDate($characterData['birthday'] ?? '2003-01-01 00:00:00') * 1000),
                'gender' => $characterData['gender'] ?? '',
                'race_id' => $characterData['race_id'] ?? 0,
                'security_status' => (float) number_format($characterData['security_status'], 2),
                'bloodline_id' => $characterData['bloodline_id'] ?? 0,
                'corporation_id' => $characterData['corporation_id'] ?? 0,
                'corporation_name' => $corporationData['name'] ?? 'Unknown',
                'alliance_id' => $allianceData['alliance_id'] ?? 0,
                'alliance_name' => $allianceData['name'] ?? '',
                'faction_id' => $factionData['faction_id'] ?? 0,
                'faction_name' => $factionData['name'] ?? '',
                'last_modified' => new UTCDateTime(),
            ];

            if ($updateHistory) {
                $characterInfo['history'] = $this->getCharacterHistory($characterId);
            } else {
                // Get the character from the database and re-attach the history if it exists
                $characterHistory = $this->characters->findOneOrNull([
                    'character_id' => $characterId,
                ], ['projection' => ['history' => 1]]);

                if ($characterHistory && isset($characterHistory['history'])) {
                    $characterInfo['history'] = $characterHistory['history'];
                }
            }

            // Completely replace the character in the database (Remove the old data)
            $this->characters->collection->replaceOne([
                'character_id' => $characterId,
            ], $characterInfo, ['upsert' => true]);

            return $characterInfo;
        } finally {
            // Remove character from processing list
            $this->processingCharacters = array_filter(
                $this->processingCharacters,
                fn($id) => $id !== $characterId
            );
        }
    }

    public function getCorporationInfo(int $corporationId, bool $forceUpdate = false, bool $updateHistory = false): array
    {
        // Check if corporation is already being processed
        if (in_array($corporationId, $this->processingCorporations, true)) {
            // Return minimal data to prevent loop
            return [
                'corporation_id' => $corporationId,
                'name' => $this->corporationNames[$corporationId] ?? 'Unknown',
            ];
        }

        // Add corporation to processing list
        $this->processingCorporations[] = $corporationId;

        try {
            $query = [
                'corporation_id' => $corporationId,
                'last_modified' => ['$gte' => new UTCDateTime(time() - (((7 * 24) * 60) * 60) * 1000)],
            ];

            if ($forceUpdate) {
                $corporationData = $this->esiCorporations->getCorporationInfo($corporationId, cacheTime: 360000);
            } else {
                $corporationData = $this->corporations->findOneOrNull($query, ['projection' => ['error' => 0]]) ?? $this->esiCorporations->getCorporationInfo($corporationId, cacheTime: 360000);
            }

            // Store the corporation name early to assist in recursive calls
            $this->corporationNames[$corporationId] = $corporationData['name'] ?? 'Unknown';

            $factionData = [];
            $allianceData = [];
            $ceoData = [];
            $creatorData = [];
            $locationData = [];
            if (isset($corporationData['faction_id']) && $corporationData['faction_id'] !== 0) {
                $factionData = $this->getFactionInfo($corporationData['faction_id']);
            }
            if (isset($corporationData['alliance_id']) && $corporationData['alliance_id'] !== 0) {
                $allianceData = $this->getAllianceInfo($corporationData['alliance_id']);
            }

            if (isset($corporationData['ceo_id']) && $corporationData['ceo_id'] !== 0) {
                $ceoData = $this->getCharacterInfo($corporationData['ceo_id']);
            }

            if (isset($corporationData['creator_id']) && $corporationData['creator_id'] !== 0) {
                $creatorData = $this->getCharacterInfo($corporationData['creator_id']);
            }

            if (isset($corporationData['home_station_id']) && $corporationData['home_station_id'] !== 0) {
                $locationData = $this->celestials->findOneOrNull(['item_id' => $corporationData['home_station_id']]);
            }

            $corporationInfo = [
                'corporation_id' => $corporationData['corporation_id'],
                'name' => $corporationData['name'],
                'ticker' => $corporationData['ticker'],
                'description' => $corporationData['description'],
                'date_founded' => new UTCDateTime($this->handleDate($corporationData['date_founded'] ?? '2003-01-01 00:00:00') * 1000),
                'alliance_id' => $corporationData['alliance_id'] ?? 0,
                'alliance_name' => $allianceData['name'] ?? 'Unknown',
                'faction_id' => $factionData['faction_id'] ?? 0,
                'faction_name' => $factionData['name'] ?? '',
                'ceo_id' => $ceoData['character_id'] ?? 0,
                'ceo_name' => $ceoData['name'] ?? 'Unknown',
                'creator_id' => $creatorData['character_id'] ?? 0,
                'creator_name' => $creatorData['name'] ?? 'Unknown',
                'home_station_id' => $corporationData['home_station_id'] ?? 0,
                'home_station_name' => $locationData['item_name'] ?? '',
                'member_count' => $corporationData['member_count'] ?? 0,
                'shares' => $corporationData['shares'] ?? 0,
                'tax_rate' => $corporationData['tax_rate'] ?? 0,
                'url' => $corporationData['url'] ?? '',
                'history' => $this->getCorporationHistory($corporationId),
                'last_modified' => new UTCDateTime(),
            ];

            if ($updateHistory) {
                $corporationInfo['history'] = $this->getCorporationHistory($corporationId);
            } else {
                // Get the corporation from the database and re-attach the history if it exists
                $corporationHistory = $this->corporations->findOneOrNull([
                    'corporation_id' => $corporationId,
                ], ['projection' => ['history' => 1]]);

                if ($corporationHistory && isset($corporationHistory['history'])) {
                    $corporationInfo['history'] = $corporationHistory['history'];
                }
            }

            $this->corporations->collection->replaceOne([
                'corporation_id' => $corporationId,
            ], $corporationInfo, ['upsert' => true]);

            return $corporationInfo;
        } finally {
            // Remove corporation from processing list
            $this->processingCorporations = array_filter(
                $this->processingCorporations,
                fn($id) => $id !== $corporationId
            );
        }
    }

    public function getAllianceInfo(int $allianceId, bool $forceUpdate = false): array
    {
        // Check if alliance is already being processed
        if (in_array($allianceId, $this->processingAlliances, true)) {
            // Return minimal data to prevent loop
            return [
                'alliance_id' => $allianceId,
                'name' => $this->allianceNames[$allianceId] ?? 'Unknown',
            ];
        }

        // Add alliance to processing list
        $this->processingAlliances[] = $allianceId;

        try {
            $query = [
                'alliance_id' => $allianceId,
                'last_modified' => ['$gte' => new UTCDateTime(time() - (((7 * 24) * 60) * 60) * 1000)],
            ];

            if ($forceUpdate) {
                $allianceData = $this->esiAlliances->getAllianceInfo($allianceId, cacheTime: 360000);
            } else {
                $allianceData = $this->alliances->findOneOrNull($query, ['projection' => ['error' => 0]]) ?? $this->esiAlliances->getAllianceInfo($allianceId, cacheTime: 360000);
            }

            // Store the alliance name early to assist in recursive calls
            $this->allianceNames[$allianceId] = $allianceData['name'] ?? 'Unknown';

            $creatorData = [];
            $creatorCorporationData = [];
            $executorCorporationData = [];
            if (isset($allianceData['creator_id']) && $allianceData['creator_id'] !== 0) {
                $creatorData = $this->getCharacterInfo($allianceData['creator_id']);
            }

            if (isset($allianceData['creator_corporation_id']) && $allianceData['creator_corporation_id'] !== 0) {
                $creatorCorporationData = $this->getCorporationInfo($allianceData['creator_corporation_id']);
            }

            if (isset($allianceData['executor_corporation_id']) && $allianceData['executor_corporation_id'] !== 0) {
                $executorCorporationData = $this->getCorporationInfo($allianceData['executor_corporation_id']);
            }

            $allianceInfo = [
                'alliance_id' => $allianceData['alliance_id'],
                'name' => $allianceData['name'],
                'ticker' => $allianceData['ticker'],
                'creator_id' => $allianceData['creator_id'],
                'creator_name' => $creatorData['name'] ?? 'Unknown',
                'creator_corporation_id' => $allianceData['creator_corporation_id'],
                'creator_corporation_name' => $creatorCorporationData['name'] ?? 'Unknown',
                'executor_corporation_id' => $allianceData['executor_corporation_id'],
                'executor_corporation_name' => $executorCorporationData['name'] ?? 'Unknown',
                'last_modified' => new UTCDateTime(),
            ];

            $this->alliances->collection->replaceOne([
                'alliance_id' => $allianceId,
            ], $allianceInfo, ['upsert' => true]);

            return $allianceInfo;
        } finally {
            // Remove alliance from processing list
            $this->processingAlliances = array_filter(
                $this->processingAlliances,
                fn($id) => $id !== $allianceId
            );
        }
    }

    public function getFactionInfo(int $factionId): array
    {
        // This is basically the same as the corporation info
        $factionData = $this->factions->findOneOrNull([
            'faction_id' => $factionId
        ]);

        $factionInfo = [
            'faction_id' => $factionData['faction_id'],
            'name' => $factionData['name'],
            'description' => $factionData['description'],
            'militia_corporation_id' => $factionData['militia_corporation_id'],
            'ceo_id' => 0,
            'creator_id' => 0,
            'home_station_id' => 0,
            'size_factor' => $factionData['size_factor'],
            'solar_system_id' => $factionData['solar_system_id'],
            'station_count' => $factionData['station_count'],
            'station_system_count' => $factionData['station_system_count'],
            'last_modified' => new UTCDateTime(),
        ];

        $this->factions->collection->updateOne([
            'faction_id' => $factionId,
        ], [
            '$set' => $factionInfo,
        ]);

        return $factionInfo;
    }

    public function getCharacterHistory(int $characterId): array
    {
        return $this->esiCharacters->getCharacterHistory($characterId, cacheTime: 3600);
    }

    public function getCorporationHistory(int $corporationId): array
    {
        return $this->esiCorporations->getCorporationHistory($corporationId, cacheTime: 3600);
    }

    private function handleDate(string|UTCDateTime $date): int
    {
        if ($date instanceof UTCDateTime) {
            return $date->toDateTime()->getTimestamp();
        }

        // Convert to unixtimestamp
        return strtotime($date);
    }
}
