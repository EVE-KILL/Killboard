<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\ESI\Wars;
use EK\Jobs\ProcessWar;
use EK\Models\Wars as WarModel;

class UpdateWars extends ConsoleCommand
{
    protected string $signature = 'update:wars';
    protected string $description = 'Updates all the wars available';

    public function __construct(
        protected Wars $esiWars,
        protected ProcessWar $warJob,
        protected WarModel $wars
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $this->out('Updating wars..');
        $wars = [];
        $minWarId = 999999999;
        $resultCount = 0;

        do {
            $warData = $this->esiWars->getWars($minWarId);
            $resultCount = count($warData);
            $wars = array_unique(array_merge($wars, $warData));
            $minWarId = !empty($warData) ? min($warData) : $minWarId;
        } while ($resultCount >= 2000);

        $this->out("Found " . count($wars) . " wars");
        $enqueuedCount = 0;

        // Chunk the wars array into pieces of 100
        $chunks = array_chunk($wars, 100);

        foreach ($chunks as $chunk) {
            $existingWars = $this->wars->find(
                ['id' => ['$in' => $chunk]],
                ['projection' => ['id' => 1]]
            )->toArray();

            $existingWarIds = array_column($existingWars, 'id');
            $newWars = array_diff($chunk, $existingWarIds);

            if (!empty($newWars)) {
                $enqueuedCount += count($newWars);
                $enqueueData = array_map(fn($warId) => ['war_id' => $warId], $newWars);
                $this->warJob->massEnqueue($enqueueData);
            }
        }

        $this->out("Enqueued " . $enqueuedCount . " wars");
    }
}
