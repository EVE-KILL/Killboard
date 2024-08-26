<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Models\Characters;
use EK\RabbitMQ\RabbitMQ;
use League\Container\Container;

class EVEWhoCharactersInCorporation extends Jobs
{
    protected string $defaultQueue = 'evewho';
    public function __construct(
        protected EveWho $eveWhoFetcher,
        protected Characters $characters,
        protected RabbitMQ $rabbitMQ,
        protected Container $container
    ) {
        parent::__construct($rabbitMQ);
    }

    public function handle(array $data): void
    {
        $evewhoCharactersJob = $this->container->get(EVEWhoCharacter::class);
        $updateCharacterJob = $this->container->get(UpdateCharacter::class);

        $corporationId = $data['corporation_id'];

        $url = "https://evewho.com/api/corplist/{$corporationId}";
        $request = $this->eveWhoFetcher->fetch($url);
        $data = $request["body"] ?? "";

        $decoded = json_validate($data) ? json_decode($data, true) : [];
        $characters = $decoded["characters"] ?? [];

        foreach ($characters as $character) {
            $characterId = $character["character_id"];
            $characterData = $this->characters->findOneOrNull(["character_id" => $characterId]);

            if ($characterData && !empty($characterData["deleted"])) {
                // If the character is marked as deleted, fetch from EVEWho
                $evewhoCharactersJob->enqueue(["character_id" => $characterId]);
            } elseif (!$characterData) {
                // Enqueue the character for updating if not found in the database
                $updateCharacterJob->enqueue(["character_id" => $characterId]);
            }
        }
    }
}
