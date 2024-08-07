<?php

namespace EK\Commands\Import;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\UpdateCharacter;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;
use League\Csv\Reader;
use MongoDB\BSON\UTCDateTime;

/**
 * @property $manualPath
 */
class EBCharacters extends ConsoleCommand
{
    protected string $signature = 'import:ebcharacters {path : The path to the CSV file} {--offset=0 : The offset to start from}';
    protected string $description = 'Import characters from the EVEBoard dump';

    public function __construct(
        protected Characters $characters,
        protected Corporations $corporations,
        protected Alliances $alliances,
        protected UpdateCharacter $updateCharacter
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        if (!file_exists($this->path)) {
            $this->out("The path to the CSV file is invalid.");
            return;
        }

        $reader = Reader::createFromPath($this->path);
        $reader->setHeaderOffset(0);

        $offset = 0;
        $records = $reader->getRecords();
        $recordCount = $reader->count() - $offset;
        $progressBar = $this->progressBar($recordCount);

        $this->out("Importing characters...");

        $characterBatch = [];
        $updateBatch = [];
        $loadedAlliances = [];
        $loadedCorporations = [];
        $batchSize = 10000;

        foreach ($records as $record) {
            if ($this->offset > 0) {
                $offset++;
                if ($offset < $this->offset) {
                    continue;
                }
            }

            $deleted = false;
            try {
                $alliance = (int) $record['allianceID'] > 0 ?
                    $loadedAlliances[$record['allianceID']] ?? $this->alliances->findOne(['alliance_id' => (int) $record['allianceID']]) : null;
                $corporation = (int) $record['corporationID'] > 0 ?
                    $loadedCorporations[$record['corporationID']] ?? $this->corporations->findOne(['corporation_id' => (int) $record['corporationID']]) : null;

                $character = [
                    'character_id' => (int) $record['characterID'],
                    'name' => $record['CharacterName'],
                    'birthday' => new UTCDateTime(strtotime($record['DoB']) * 1000),
                    'bloodline_id' => match($record['bloodLine']) {
                        'Amarr' => 5,
                        'Modifier' => 10,
                        'Narodnya' => 25,
                        'Deteis' => 1,
                        'Ni-Kunni' => 6,
                        'Achura' => 11,
                        'Koschoi' => 26,
                        'Civire' => 2,
                        'Gallente' => 7,
                        'Jin-Mei' => 12,
                        'Navka' => 27,
                        'Sebiestor' => 3,
                        'Intaki' => 8,
                        'Khanid' => 13,
                        'Brutor' => 4,
                        'Static' => 9,
                        'Vherokior' => 14,
                        'Drifter' => 19,
                        '' => 0
                    },
                    'gender' => $record['gender'],
                    'race_id' => match ($record['race']) {
                        'Caldari' => 1,
                        'Minmatar' => 2,
                        'Amarr' => 4,
                        'Gallente' => 8,
                        'Jove' => 16,
                        'Triglavian' => 135,
                        '' => 0
                    },
                    'security_status' => (float) $record['securityStatus'],
                    'alliance_name' => $alliance ? $alliance->get('name', '') : '',
                    'alliance_id' => (int) $record['allianceID'] ?? 0,
                    'corporation_id' => (int) $record['corporationID'] ?? 0,
                    'corporation_name' => $corporation ? $corporation->get('name', '') : '',
                ];

                if ((int) $record['corporationID'] === 1000001) {
                    $deleted = true;
                    $character['deleted'] = true;
                }

                $characterBatch[] = $character;

                if ($deleted === false) {
                    $updateBatch[] = ['character_id' => $record['characterID']];
                }

                if (count($characterBatch) >= $batchSize) {
                    $this->characters->setData($characterBatch);
                    $this->characters->saveMany();

                    $this->updateCharacter->massEnqueue($updateBatch);
                    $characterBatch = [];
                    $updateBatch = [];
                }
            } catch(\Exception $e) {
                $this->out("Failed to import character {$record['CharacterName']}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        if (!empty($characterBatch)) {
            $this->characters->setData($characterBatch);
            $this->characters->saveMany();
        }

        if (!empty($updateBatch)) {
            $this->updateCharacter->massEnqueue($updateBatch);
        }

        $progressBar->finish();
    }
}
