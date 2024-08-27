<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
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
        protected \EK\Models\Characters $characters,
        protected ESI $esiFetcher
    ) {
    }

    public function getCharacterInfo(int $characterID): array
    {
        if ($characterID < 10000) {
            return [];
        }

        $characterData = $this->esiFetcher->fetch('/latest/characters/' . $characterID);
        $characterData = json_validate($characterData['body']) ? json_decode($characterData['body'], true) : [];
        $characterData['character_id'] = $characterID;
        if (isset($characterData['birthday'])) {
            $characterData['birthday'] = new UTCDateTime(strtotime($characterData['birthday']) * 1000);
        }

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        return $characterData;
    }
}
