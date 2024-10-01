<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Models\Characters as ModelsCharacters;
use League\Container\Container;
use MongoDB\BSON\UTCDateTime;

class Characters
{
    public function __construct(
        protected Container $container,
        protected ModelsCharacters $characters,
        protected ESI $esiFetcher
    ) {
    }

    public function getCharacterInfo(int $characterId, int $cacheTime = 300): array
    {
        if ($characterId < 10000) {
            return [
                'character_id' => $characterId,
                'name' => 'Unknown',
                'corporation_id' => 0,
                'alliance_id' => 0,
                'faction_id' => 0,
            ];
        }

        $characterData = $this->esiFetcher->fetch('/latest/characters/' . $characterId, cacheTime: $cacheTime);
        $characterData = json_validate($characterData['body']) ? json_decode($characterData['body'], true) : [];
        $characterData['character_id'] = $characterId;

        ksort($characterData);

        return $characterData;
    }

    public function getCharacterHistory(int $characterId, int $cacheTime = 300): array
    {
        $characterHistory = $this->esiFetcher->fetch('/latest/characters/' . $characterId . '/corporationhistory', cacheTime: $cacheTime);
        $characterHistory = json_validate($characterHistory['body']) ? json_decode($characterHistory['body'], true) : [];

        ksort($characterHistory);

        return $characterHistory;
    }
}
