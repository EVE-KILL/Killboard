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

    public function getCharacterInfo(int $characterId, bool $updateHistory = false): array
    {
        $query = [
            'character_id' => $characterId,
            'last_modified' => ['$gte' => new UTCDateTime(time() - (((30 * 24) * 60) * 60) * 1000)],
            'name' => ['$nin' => ['Unknown', '', null]],
            'bloodline_id' => ['$exists' => true],
            'race_id' => ['$exists' => true],
            'security_status' => ['$exists' => true],
        ];

        $this->dump("Character getting info for $characterId");
        $characterData = $this->characters->findOneOrNull($query, ['projection' => ['_id' => 0, 'error' => 0]]);
        if ($characterData === null) {
            $this->dump("Character not found in database, getting from ESI");
            $characterData = $this->esiCharacters->getCharacterInfo($characterId);
            if (isset($characterData['deleted']) && $characterData['deleted'] === true) {
                $this->dump("Character is deleted, returning deleted character info");
                return $this->deletedCharacterInfo($characterId);
            }
        }

        $error = $characterData['error'] ?? null;
        if ($error) {
            switch ($error) {
                case 'Character has been deleted!':
                    return $this->deletedCharacterInfo($characterId);
                case 'Character not found':
                    return $this->deletedCharacterInfo($characterId, false);
                default:
                    throw new \Exception("Error occurred while fetching character info: $error");
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
                foreach ($errors as $key => $error) {
                    $errorString .= "{$key}: {$error[0]} ";
                }
                return $errorString;
            };
            dump($characterData);
            throw new \Exception("Error occurred while fetching character info: " . $errors($validator->getMessages()));
        }

        $factionData = [];
        $corporationId = $characterData['corporation_id'];
        $allianceId = $characterData['alliance_id'] ?? 0;
        $factionId = $characterData['faction_id'] ?? 0;
        if (is_numeric($factionId) && $factionId > 0) {
            $factionData = $this->getFactionInfo($factionId);
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
            'corporation_id' => $corporationId,
            'alliance_id' => $allianceId,
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
    }

    public function getCorporationInfo(int $corporationId, bool $updateHistory = false): array
    {
        $query = [
            'corporation_id' => $corporationId,
            'last_modified' => ['$gte' => new UTCDateTime(time() - (((30 * 24) * 60) * 60) * 1000)],
            'name' => ['$nin' => ['Unknown', '', null]],
            'ticker' => ['$nin' => ['Unknown', '', null]],
            'ceo_id' => ['$ne' => 1],
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
                foreach ($errors as $key => $error) {
                    $errorString .= "{$key}: {$error[0]} ";
                }
                return $errorString;
            };
            dump($corporationData);
            throw new \Exception("Error occurred while fetching corporation info: " . $errors($validator->getMessages()));
        }

        $factionData = [];
        $locationData = [];
        $factionId = $corporationData['faction_id'] ?? 0;
        if (is_numeric($factionId) && $factionId > 0) {
            $factionData = $this->getFactionInfo($factionId);
        }

        $allianceId = $corporationData['alliance_id'] ?? 0;
        $ceoId = $corporationData['ceo_id'] ?? 0;
        $creatorId = $corporationData['creator_id'] ?? 0;

        $homeStationId = $corporationData['home_station_id'] ?? 0;
        if (is_numeric($homeStationId) && $homeStationId > 0) {
            $this->dump("Corporation getting home station info for $homeStationId");
            $locationData = $this->celestials->findOneOrNull(['item_id' => $homeStationId]);
        }

        $corporationInfo = [
            'corporation_id' => $corporationData['corporation_id'],
            'name' => $corporationData['name'],
            'ticker' => $corporationData['ticker'],
            'description' => $corporationData['description'],
            'date_founded' => new UTCDateTime($this->handleDate($corporationData['date_founded'] ?? '2003-01-01 00:00:00') * 1000),
            'alliance_id' => $allianceId,
            'faction_id' => $factionId,
            'faction_name' => $factionData['name'] ?? '',
            'ceo_id' => $ceoId,
            'creator_id' => $creatorId,
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

        // Completely replace the corporation in the database (Remove the old data)
        $this->corporations->collection->replaceOne([
            'corporation_id' => $corporationId,
        ], $corporationInfo, ['upsert' => true]);

        return $corporationInfo;
    }

    public function getAllianceInfo(int $allianceId): array
    {
        $query = [
            'alliance_id' => $allianceId,
            'last_modified' => ['$gte' => new UTCDateTime(time() - (((30 * 24) * 60) * 60) * 1000)],
            'name' => ['$nin' => ['Unknown', '', null]],
            'ticker' => ['$nin' => ['Unknown', '', null]]
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
                foreach ($errors as $key => $error) {
                    $errorString .= "{$key}: {$error[0]} ";
                }
                return $errorString;
            };
            dump($allianceData);
            throw new \Exception("Error occurred while fetching alliance info: " . $errors($validator->getMessages()));
        }

        $creatorId = $allianceData['creator_id'] ?? 0;
        $creatorCorporationId = $allianceData['creator_corporation_id'] ?? 0;
        $executorCorporationId = $allianceData['executor_corporation_id'] ?? 0;

        $allianceInfo = [
            'alliance_id' => $allianceData['alliance_id'],
            'name' => $allianceData['name'],
            'ticker' => $allianceData['ticker'],
            'creator_id' => $creatorId,
            'creator_corporation_id' => $creatorCorporationId,
            'executor_corporation_id' => $executorCorporationId,
            'last_modified' => new UTCDateTime(),
        ];

        $this->alliances->collection->replaceOne([
            'alliance_id' => $allianceId,
        ], $allianceInfo, ['upsert' => true]);

        return $allianceInfo;
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

    /**
     * Retrieves information for a deleted character.
     *
     * @param int $characterId The ID of the deleted character.
     * @param bool $exists Indicates if the character was found.
     * @return array The information of the deleted character.
     */
    protected function deletedCharacterInfo(int $characterId, bool $deleted = true): array
    {
        $existingData = $this->characters->findOneOrNull([
            'character_id' => $characterId,
        ]);

        $deletedCharacter = [
            'character_id' => $characterId,
            'name' => $existingData['name'] ?? 'Deleted',
            'description' => $existingData['description'] ?? '',
            'birthday' => new UTCDateTime($this->handleDate($existingData['birthday'] ?? '2003-01-01 00:00:00') * 1000),
            'gender' => $existingData['gender'] ?? '',
            'race_id' => $existingData['race_id'] ?? 0,
            'security_status' => (float) number_format($existingData['security_status'] ?? 0, 2),
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

    private function dump(mixed $data): void
    {
        dump($data);
    }
}
