<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use League\Container\Container;

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

        try {
            $characterData = $this->esiFetcher->fetch('/latest/characters/' . $characterID);
            $characterData = json_validate($characterData['body']) ? json_decode($characterData['body'], true) : [];
        } catch (\Exception $e) {
            $characterData = [
                'name' => 'Unknown',
                'corporation_id' => 0,
                'alliance_id' => 0,
                'faction_id' => 0,
                'security_status' => 0,
                'birthday' => '1970-01-01T00:00:00Z',
            ];
        }
        $characterData['character_id'] = $characterID;

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        $updateCharacter = $this->container->get(\EK\Jobs\UpdateCharacter::class);
        $updateCharacter->enqueue(['character_id' => $characterData['character_id']]);

        return $characterData;
    }
}
