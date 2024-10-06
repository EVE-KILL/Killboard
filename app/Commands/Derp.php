<?php

namespace EK\Commands;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\ESIData;
use EK\Jobs\UpdateAlliance;
use EK\Jobs\UpdateCharacter;
use EK\Jobs\UpdateCorporation;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @property $manualPath
 */
class Derp extends ConsoleCommand
{
    protected string $signature = 'Derp';
    protected string $description = '';

    public function __construct(
        protected ESIData $eSIData,
        protected Characters $characters,
        protected Corporations $corporations,
        protected Alliances $alliances,
        protected UpdateCharacter $updateCharacter,
        protected UpdateCorporation $updateCorporation,
        protected UpdateAlliance $updateAlliance,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $alliances = $this->alliances->find(['$or' => [
            ['name' => null],
            ['name' => ''],
            ['name' => 'Unknown'],
        ]]);

        $alliances = iterator_to_array($alliances);
        $this->out('Found ' . count($alliances) . ' alliances to update');
        $progressBar = $this->progressBar(count($alliances));
        foreach ($alliances as $alliance) {
            $this->updateAlliance->enqueue(['alliance_id' => $alliance['alliance_id']]);
            $progressBar->advance();
        }
        $progressBar->finish();
        $corporations = $this->corporations->find(['$or' => [
            ['name' => null],
            ['name' => ''],
            ['name' => 'Unknown'],
        ], 'ceo_id' => ['$ne' => 1]]);
        $corporations = iterator_to_array($corporations);
        $this->out('Found ' . count($corporations) . ' corporations to update');
        $progressBar = $this->progressBar(count($corporations));
        foreach ($corporations as $corporation) {
            $this->updateCorporation->enqueue(['corporation_id' => $corporation['corporation_id'], 'update_history' => true]);
            $progressBar->advance();
        }
        $progressBar->finish();

        $characters = $this->characters->find(['$or' => [
            ['name' => null],
            ['name' => ''],
            ['name' => 'Unknown'],
        ], 'deleted' => ['$ne' => true]]);
        $characters = iterator_to_array($characters);
        $this->out('Found ' . count($characters) . ' characters to update');
        $progressBar = $this->progressBar(count($characters));
        foreach ($characters as $character) {
            $this->updateCharacter->enqueue(['character_id' => $character['character_id'], 'update_history' => true]);
            $progressBar->advance();
        }
        $progressBar->finish();
        //        // 268946627
        //        // 2112817470
        //        $data = $this->eSIData->getCharacterInfo(268946627, true, true);
        //        dump($data);
    }
}
