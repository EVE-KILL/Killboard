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
use Sirius\Validation\Validator;

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

    protected function deletedCharacterInfo(int $characterId, bool $deleted = true): array
    {
        $existingData = $this->characters->findOneOrNull([
            'character_id' => $characterId,
        ]);

        if ($existingData === null) {
            $returnData = [
                'character_id' => $characterId,
                'name' => 'Deleted',
                'corporation_id' => 1000001,
                'corporation_name' => 'Doomheim',
                'alliance_id' => 0,
                'faction_id' => 0,
                'bloodline_id' => 0,
                'race_id' => 0,
                'security_status' => 0,
                'last_modified' => new UTCDateTime(),
            ];

            if ($deleted) {
                $returnData['deleted'] = true;
            }

            return $returnData;
        }

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

        if ($deleted) {
            $deletedCharacter['deleted'] = true;
        }

        $this->characters->collection->replaceOne([
            'character_id' => $characterId,
        ], $deletedCharacter, ['upsert' => true]);

        return $deletedCharacter;
    }

    public function getCharacterInfo(int $characterId, bool $updateHistory = false, ?string $cameFrom = null): array
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
                'name' => ['$nin' => ['Unknown', '', null]],
                'corporation_name' => ['$nin' => ['Unknown', '', null]],
            ];

            $characterData = $this->characters->findOneOrNull($query, ['projection' => ['_id' => 0, 'error' => 0]]);
            if ($characterData === null) {
                $characterData = $this->esiCharacters->getCharacterInfo($characterId);
                if (isset($characterData['deleted']) && $characterData['deleted'] === true) {
                    return $this->deletedCharacterInfo($characterId);
                }
            }

            $error = isset($characterData['error']) ? $characterData['error'] : null;
            if ($error) {
                switch ($error) {
                    case 'Character has been deleted!':
                        return $this->deletedCharacterInfo($characterId);
                        break;
                    case 'Character not found':
                        return $this->deletedCharacterInfo($characterId, false);
                        break;
                    default:
                        throw new \Exception("Error occurred while fetching character info: $error");
                        break;
                }
            }

            // Validate the character data
            $validator = new Validator();
            $validator->add('name', 'required');
            $validator->add('corporation_id', 'required');
            $validator->add('bloodline_id', 'required');
            $validator->add('race_id', 'required');
            $validator->add('security_status', 'required');

            if (!$validator->validate($characterData)) {
                $errors = function ($errors) {
                    $errorString = '';
                    foreach($errors as $key => $error) {
                        $errorString .= (string) "$key: $error[0]";
                    }
                    return $errorString;
                };
                dump($characterData);
                throw new \Exception("Error occurred while fetching character info: " . $errors($validator->getMessages()));
            }

            // Store the character name early to assist in recursive calls
            $this->characterNames[$characterId] = $characterData['name'] ?? 'Unknown';

            $corporationData = [];
            $factionData = [];
            $allianceData = [];

            $corporationId = $characterData['corporation_id'];
            if (is_numeric($corporationId) && $corporationId > 0 && $cameFrom !== 'corporation') {
                $corporationData = $this->getCorporationInfo($corporationId, $updateHistory, $cameFrom ?? 'character');
            }

            $factionId = $characterData['faction_id'] ?? 0;
            if (is_numeric($factionId) && $factionId > 0) {
                $factionData = $this->getFactionInfo($factionId, $cameFrom ?? 'character');
            }
            $allianceId = $characterData['alliance_id'] ?? 0;
            if (is_numeric($allianceId) && $allianceId > 0 && $cameFrom !== 'alliance') {
                $allianceData = $this->getAllianceInfo($allianceId, $updateHistory, $cameFrom ?? 'character');
            }

            $characterInfo = [
                'character_id' => $characterData['character_id'],
                'name' => $characterData['name'],
                'description' => $characterData['description'] ?? '',
                'birthday' => new UTCDateTime($this->handleDate($characterData['birthday'] ?? '2003-01-01 00:00:00') * 1000),
                'gender' => $characterData['gender'] ?? '',
                'race_id' => $characterData['race_id'] ?? 0,
                'security_status' => (float) number_format($characterData['security_status'], 2) ?? 0,
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
                fn ($id) => $id !== $characterId
            );
        }
    }

    public function getCorporationInfo(int $corporationId, bool $updateHistory = false, ?string $cameFrom = null): array
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
                'last_modified' => ['$gte' => new UTCDateTime(time() - (((30 * 24) * 60) * 60) * 1000)],
                'name' => ['$nin' => ['Unknown', '', null]],
                'ticker' => ['$nin' => ['Unknown', '', null]],
                'ceo_name' => ['$nin' => ['Unknown', '', null]],
                'creator_name' => ['$nin' => ['Unknown', '', null]],
            ];

            $corporationData = $this->corporations->findOneOrNull($query, ['projection' => ['error' => 0]]);
            if ($corporationData === null) {
                $corporationData = $this->esiCorporations->getCorporationInfo($corporationId);
            }

            $validator = new Validator();
            $validator->add('name', 'required');
            $validator->add('ticker', 'required');
            $validator->add('ceo_id', 'required');
            $validator->add('creator_id', 'required');

            if (!$validator->validate($corporationData)) {
                $errors = function ($errors) {
                    $errorString = '';
                    foreach($errors as $key => $error) {
                        $errorString .= (string) "$key: $error[0]";
                    }
                    return $errorString;
                };
                dump($corporationData);
                throw new \Exception("Error occurred while fetching corporation info: " . $errors($validator->getMessages()));
            }

            // Store the corporation name early to assist in recursive calls
            $this->corporationNames[$corporationId] = $corporationData['name'] ?? 'Unknown';

            $factionData = [];
            $allianceData = [];
            $ceoData = [];
            $creatorData = [];
            $locationData = [];

            $factionId = $corporationData['faction_id'] ?? 0;
            if (is_numeric($factionId) && $factionId > 0) {
                $factionData = $this->getFactionInfo($factionId);
            }

            $allianceId = $corporationData['alliance_id'] ?? 0;
            if (is_numeric($allianceId) && $allianceId > 0 && $cameFrom !== 'alliance') {
                $allianceData = $this->getAllianceInfo($allianceId, $updateHistory, $cameFrom ?? 'corporation');
            }

            $ceoId = $corporationData['ceo_id'] ?? 0;
            if (is_numeric($ceoId) && $ceoId > 0 && $cameFrom !== 'character') {
                $ceoData = $this->getCharacterInfo($ceoId, $updateHistory, $updateHistory, $cameFrom ?? 'corporation');
            }

            $creatorId = $corporationData['creator_id'] ?? 0;
            if (is_numeric($creatorId) && $creatorId > 0 && $cameFrom !== 'character') {
                $creatorData = $this->getCharacterInfo($creatorId, $updateHistory, $updateHistory, $cameFrom ?? 'corporation');
            }

            $homeStationId = $corporationData['home_station_id'] ?? 0;
            if (is_numeric($homeStationId) && $homeStationId > 0) {
                $locationData = $this->celestials->findOneOrNull(['item_id' => $homeStationId]);
            }

            $corporationInfo = [
                'corporation_id' => $corporationData['corporation_id'],
                'name' => $corporationData['name'],
                'ticker' => $corporationData['ticker'],
                'description' => $corporationData['description'],
                'date_founded' => new UTCDateTime($this->handleDate($corporationData['date_founded'] ?? '2003-01-01 00:00:00') * 1000),
                'alliance_id' => $allianceId,
                'alliance_name' => $allianceData['name'] ?? 'Unknown',
                'faction_id' => $factionId,
                'faction_name' => $factionData['name'] ?? '',
                'ceo_id' => $ceoId,
                'ceo_name' => $ceoData['name'] ?? 'Unknown',
                'creator_id' => $creatorId,
                'creator_name' => $creatorData['name'] ?? 'Unknown',
                'home_station_id' => $homeStationId,
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
                fn ($id) => $id !== $corporationId
            );
        }
    }

    public function getAllianceInfo(int $allianceId, bool $updateHistory = false, ?string $cameFrom = null): array
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
                'last_modified' => ['$gte' => new UTCDateTime(time() - (((30 * 24) * 60) * 60) * 1000)],
                'name' => ['$nin' => ['Unknown', '', null]],
                'ticker' => ['$nin' => ['Unknown', '', null]],
                'creator_name' => ['$nin' => ['Unknown', '', null]],
                'creator_corporation_name' => ['$nin' => ['Unknown', '', null]],
            ];

            $allianceData = $this->alliances->findOneOrNull($query, ['projection' => ['error' => 0]]);
            if ($allianceData === null) {
                $allianceData = $this->esiAlliances->getAllianceInfo($allianceId);
            }

            $validator = new Validator();
            $validator->add('name', 'required');
            $validator->add('ticker', 'required');
            $validator->add('creator_id', 'required');
            $validator->add('creator_corporation_id', 'required');

            if (!$validator->validate($allianceData)) {
                $errors = function ($errors) {
                    $errorString = '';
                    foreach($errors as $key => $error) {
                        $errorString .= (string) "$key: $error[0]";
                    }
                    return $errorString;
                };
                dump($allianceData);
                throw new \Exception("Error occurred while fetching alliance info: " . $errors($validator->getMessages()));
            }

            // Store the alliance name early to assist in recursive calls
            $this->allianceNames[$allianceId] = $allianceData['name'] ?? 'Unknown';

            $creatorData = [];
            $creatorCorporationData = [];
            $executorCorporationData = [];

            $creatorId = $allianceData['creator_id'] ?? 0;
            if (is_numeric($creatorId) && $creatorId > 0 && $cameFrom !== 'character') {
                $creatorData = $this->getCharacterInfo($creatorId, $updateHistory, $cameFrom ?? 'alliance');
            }

            $creatorCorporationId = $allianceData['creator_corporation_id'] ?? 0;
            if (is_numeric($creatorCorporationId) && $creatorCorporationId > 0 && $cameFrom !== 'corporation') {
                $creatorCorporationData = $this->getCorporationInfo($creatorCorporationId, $updateHistory, $cameFrom ?? 'alliance');
            }

            $executorCorporationId = $allianceData['executor_corporation_id'] ?? 0;
            if (is_numeric($executorCorporationId) && $executorCorporationId > 0 && $cameFrom !== 'corporation') {
                $executorCorporationData = $this->getCorporationInfo($executorCorporationId, $updateHistory, $cameFrom ?? 'alliance');
            }

            $allianceInfo = [
                'alliance_id' => $allianceData['alliance_id'],
                'name' => $allianceData['name'],
                'ticker' => $allianceData['ticker'],
                'creator_id' => $creatorId,
                'creator_name' => $creatorData['name'] ?? 'Unknown',
                'creator_corporation_id' => $creatorCorporationId,
                'creator_corporation_name' => $creatorCorporationData['name'] ?? 'Unknown',
                'executor_corporation_id' => $executorCorporationId,
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
                fn ($id) => $id !== $allianceId
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
            'faction_id' => $factionData['faction_id'] ?? 0,
            'name' => $factionData['name'] ?? 'Unknown',
            'description' => $factionData['description'] ?? '',
            'militia_corporation_id' => $factionData['militia_corporation_id'] ?? 0,
            'ceo_id' => $factionData['ceo_id'] ?? 0,
            'creator_id' => $factionData['creator_id'] ?? 0,
            'home_station_id' => $factionData['home_station_id'] ?? 0,
            'size_factor' => $factionData['size_factor'] ?? 0,
            'solar_system_id' => $factionData['solar_system_id'] ?? 0,
            'station_count' => $factionData['station_count'] ?? 0,
            'station_system_count' => $factionData['station_system_count'] ?? 0,
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
