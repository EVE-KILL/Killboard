<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\ESI\Wars as ESIWars;
use EK\Jobs\ProcessWar;
use EK\Logger\StdOutLogger;
use EK\Models\Wars as ModelsWars;

class Wars extends Cronjob
{
    protected string $cronTime = '0 * * * *';

    public function __construct(
        protected ModelsWars $warsModel,
        protected ESIWars $esiWars,
        protected ProcessWar $processWar,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        // We need to get all the wars that are newer than the latest war we have in the database
        $latestWar = $this->warsModel->findOne([], ['sort' => ['id' => -1]]);
        $latestWarId = $latestWar['id'] ?? 999999999;

        // Get the wars from the ESI and insert them into the database
        $warData = $this->esiWars->getWars($latestWarId);

        foreach ($warData as $warId) {
            if (is_int($warId)) {
                $this->processWar->enqueue(['war_id' => $warId]);
            }
        }

        // Now we need to get all the wars that are still active, so we can update them
        // We can simply look for any war that doesn't have the finished field
        $unfinishedWars = $this->warsModel->find(['finished' => ['$exists' => false]]);
        foreach($unfinishedWars as $war) {
            $this->processWar->enqueue(['war_id' => $war['id']]);
        }
    }
}
