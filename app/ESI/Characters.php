<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Models\Characters as ModelsCharacters;
use League\Container\Container;
use MongoDB\BSON\UTCDateTime;

class Characters
{
    // received response code 404 and error [GET:404] https://esi.evetech.net/v5/characters/1623529470/
    // {"error":"Character has been deleted!"}

    // If a character has been deleted, it can still be found somewhat
    // By poking /latest/universe/names/ which will get something like [{"category": "character","id": <id>,"name": "<name>"}]
    // From there the rest of the characters information can be filled in manually as placeholder data

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
