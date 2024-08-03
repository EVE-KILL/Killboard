<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;

class Wars extends Cronjob
{
    protected string $cronTime = '0 * * * *';

    public function __construct(
        protected \EK\Models\Wars $warsModel,
        protected \EK\ESI\Wars $esiWars,
        protected \EK\Jobs\ProcessWar $processWar
    ) {
    }

    public function handle(): void
    {
        // We need to get all the wars that are newer than the latest war we have in the database
        $latestWar = $this->warsModel->findOne([], ['sort' => ['id' => -1]]);
        $latestWarId = $latestWar['id'] ?? 999999999;

        // Get the wars from the ESI and insert them into the database
        $warData = $this->esiWars->getWars($latestWarId);
        $data = json_decode($warData['body'], true);

        foreach ($data as $warId) {
            $this->processWar->enqueue(['war_id' => $warId]);
        }

        // Now we need to get all the wars that are still active, so we can update them
        // We can simply look for any war that doesn't have the finished field
        $unfinishedWars = $this->warsModel->find(['finished' => ['$exists' => false]]);
        foreach($unfinishedWars as $war) {
            $this->processWar->enqueue(['war_id' => $war['id']]);
        }
    }
}
