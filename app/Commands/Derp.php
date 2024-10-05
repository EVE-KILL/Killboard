<?php

namespace EK\Commands;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\ESIData;
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
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        retry:

        try {
            $alliances = $this->alliances->find(['$or' => [
                ['name' => null],
                ['name' => ''],
                ['name' => 'Unknown'],
                ['creator_name' => null],
                ['creator_name' => ''],
                ['creator_name' => 'Unknown'],
                ['creator_corporation_name' => null],
                ['creator_corporation_name' => ''],
                ['creator_corporation_name' => 'Unknown'],
                ['executor_corporation_name' => null],
                ['executor_corporation_name' => '']
            ]]);

            $alliances = iterator_to_array($alliances);
            $this->out('Found ' . count($alliances) . ' alliances to update');
            $progressBar = $this->progressBar(count($alliances));
            foreach ($alliances as $alliance) {
                $this->eSIData->getAllianceInfo($alliance['alliance_id']);
                $progressBar->setMessage('Updating alliance: ' . $alliance['name'] . ' ' . $alliance['alliance_id']);
                $progressBar->advance();
            }
            $progressBar->finish();
            $corporations = $this->corporations->find(['$or' => [
                ['name' => null],
                ['name' => ''],
                ['name' => 'Unknown'],
                ['creator_name' => null],
                ['creator_name' => ''],
                ['ceo_name' => null],
                ['ceo_name' => ''],
                ['ceo_name' => 'Unknown'],
            ]]);
            $corporations = iterator_to_array($corporations);
            $this->out('Found ' . count($corporations) . ' corporations to update');
            $progressBar = $this->progressBar(count($corporations));
            foreach ($corporations as $corporation) {
                $this->eSIData->getCorporationInfo($corporation['corporation_id']);
                $progressBar->setMessage('Updating corporation: ' . $corporation['name'] . ' ' . $corporation['corporation_id']);
                $progressBar->advance();
            }
            $progressBar->finish();

            $characters = $this->characters->find(['$or' => [
                ['name' => null],
                ['name' => ''],
                ['name' => 'Unknown'],
                ['corporation_name' => null],
                ['corporation_name' => ''],
                ['corporation_name' => 'Unknown'],
            ], 'deleted' => ['$ne' => true]]);
            $characters = iterator_to_array($characters);
            $this->out('Found ' . count($characters) . ' characters to update');
            $progressBar = $this->progressBar(count($characters));
            foreach ($characters as $character) {
                $this->eSIData->getCharacterInfo($character['character_id'], true);
                $progressBar->setMessage('Updating character: ' . $character['name'] . ' ' . $character['character_id']);
                $progressBar->advance();
            }
            $progressBar->finish();
        } catch (\Exception $e) {
            $this->out('Error: ' . $e->getMessage());
            sleep(10);
            goto retry;
        }
        //        // 268946627
        //        // 2112817470
        //        $data = $this->eSIData->getCharacterInfo(268946627, true, true);
        //        dump($data);
    }
}
