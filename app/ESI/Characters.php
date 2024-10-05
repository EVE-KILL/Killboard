<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Fetchers\History;
use EK\Models\Characters as ModelsCharacters;
use League\Container\Container;

class Characters
{
    public function __construct(
        protected Container $container,
        protected ModelsCharacters $characters,
        protected ESI $esiFetcher,
        protected History $history
    ) {
    }

    public function getCharacterInfo(int $characterId): array
    {
        $characterData = $this->esiFetcher->fetch('/latest/characters/' . $characterId);
        $characterData = json_validate($characterData['body']) ? json_decode($characterData['body'], true) : [];
        $characterData['character_id'] = $characterId;

        ksort($characterData);

        return $characterData;
    }

    public function getCharacterHistory(int $characterId, int $cacheTime = 300): array
    {
        $characterHistory = $this->history->fetch('/latest/characters/' . $characterId . '/corporationhistory', cacheTime: $cacheTime);
        $characterHistory = json_validate($characterHistory['body']) ? json_decode($characterHistory['body'], true) : [];

        ksort($characterHistory);

        return $characterHistory;
    }
}
