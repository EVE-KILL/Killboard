<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Cache\Cache;
use EK\Jobs\CharacterScrape;
use EK\Models\Characters;

class CharacterScraper extends Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected Characters $characters,
        protected CharacterScrape $characterScrape,
        protected Cache $cache
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // Get the biggest characterId from the database
        $largestCharacterId = $this->characters->findOne([], ['sort' => ['character_id' => -1]])['character_id'] ?? 0;

        // Generate an array of characterIds to scrape (Largest +100)
        $characterIds = range($largestCharacterId + 1, $largestCharacterId + 25);

        // Enqueue the character update jobs
        $this->characterScrape->massEnqueue(array_map(fn($characterId) => ['character_id' => $characterId], $characterIds));
    }
}
