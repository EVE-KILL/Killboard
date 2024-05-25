<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;
use League\Container\Container;

class Characters extends ESIInterface
{
    // received response code 404 and error [GET:404] https://esi.evetech.net/v5/characters/1623529470/
    // {"error":"Character has been deleted!"}

    // If a character has been deleted, it can still be found somewhat
    // By poking /latest/universe/names/ which will get something like [{"category": "character","id": <id>,"name": "<name>"}]
    // From there the rest of the characters information can be filled in manually as placeholder data

    public function __construct(
        protected Container $container,
        protected \EK\Models\Characters $characters,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getCharacterInfo(int $characterID): array
    {
        $characterData = $this->fetch('/latest/characters/' . $characterID);
        $characterData['character_id'] = $characterID;

        ksort($characterData);

        $this->characters->setData($characterData);
        $this->characters->save();

        $updateCharacter = $this->container->get(\EK\Jobs\updateCharacter::class);
        $updateCharacter->enqueue(['character_id' => $characterData['character_id']]);

        return $characterData;
    }
}
