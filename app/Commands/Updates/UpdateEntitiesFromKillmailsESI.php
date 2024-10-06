<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\UpdateAlliance;
use EK\Jobs\UpdateCharacter;
use EK\Jobs\UpdateCorporation;
use EK\Models\KillmailsESI;

class UpdateEntitiesFromKillmailsESI extends ConsoleCommand
{
    protected string $signature = 'update:entities
        { --all : All entities }
    ';
    protected string $description = 'Gets all the unique character, corporation, alliance from the killmails_esi collection';

    public function __construct(
        protected KillmailsESI $killmailsESI,
        protected UpdateCharacter $updateCharacter,
        protected UpdateCorporation $updateCorporation,
        protected UpdateAlliance $updateAlliance,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        ini_set('memory_limit', '-1');
        $types = ['character', 'corporation', 'alliance'];
        foreach ($types as $type) {
            $this->out("Processing {$type}");

            $aggregateResult = $this->killmailsESI->aggregate([
                ['$group' => ['_id' => '$victim.' . $type . '_id']],
                // Filter out the IDs that exist in the $type .'s' collection
                ['$lookup' => [
                    'from' => $type . 's',
                    'localField' => '_id',
                    'foreignField' => $type . '_id',
                    'as' => 'exists'
                ]],
                ['$match' => ['exists' => ['$eq' => []]]]
            ]);

            $entities = [];
            foreach ($aggregateResult as $item) {
                $entities[] = $item['_id'];
            }

            $this->out("Found " . count($entities) . " unique {$type} entities");

            foreach ($entities as $entity) {
                $this->enqueue($type, $entity);
            }
        }
    }

    private function enqueue(string $type, int $entityId): void
    {
        switch ($type) {
            case 'character':
                $this->updateCharacter->enqueue(['character_id' => $entityId, 'update_history' => true]);
                break;
            case 'corporation':
                $this->updateCorporation->enqueue(['corporation_id' => $entityId, 'update_history' => true]);
                break;
            case 'alliance':
                $this->updateAlliance->enqueue(['alliance_id' => $entityId]);
                break;
        }
    }
}
