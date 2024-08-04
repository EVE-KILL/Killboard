<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\ESI\Wars;
use EK\Jobs\ProcessWar;
use EK\Jobs\UpdateAlliance;
use EK\Models\Alliances;

class UpdateWars extends ConsoleCommand
{
    protected string $signature = 'update:wars';
    protected string $description = 'Updates all the wars available';

    public function __construct(
        protected \EK\Models\Wars $wars,
        protected Wars $esiWars,
        protected ProcessWar $warJob
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $wars = [];
        $minWarId = 999999999;
        $resultCount = 0;

        do {
            $warData = $this->esiWars->getWars($minWarId);
            $resultCount = count($warData);
            $wars = array_unique(array_merge($wars, $warData));
            $minWarId = !empty($warData) ? min($warData) : $minWarId;
        } while ($resultCount >= 2000);

        foreach ($wars as $warId) {
            if ($this->wars->findOneOrNull(['war_id' => $warId]) === null) {
                $this->out("War $warId not found, enqueuing for processing");
                $this->warJob->enqueue(['war_id' => $warId]);
            }
        }
    }
}
