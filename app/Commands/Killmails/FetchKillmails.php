<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Http\Fetcher;
use EK\Models\Killmails;
use Illuminate\Support\Collection;

class FetchKillmails extends ConsoleCommand
{
    public string $signature = 'fetch:killmails';
    public string $description = 'Fetch all the killmails available in the zKillboard History API';

    public function __construct(
        protected ClassLoader $autoloader,
        protected Killmails $killmails,
        protected Fetcher $fetcher
    ) {
        parent::__construct();
    }

    protected function fetchAndCacheData($date): array
    {
        $file = BASE_DIR . "/cache/zkb/{$date}.json";
        if(!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        } else {
            $this->out("Fetching from: https://zkillboard.com/api/history/{$date}.json");
            $data = $this->fetcher->fetch("https://zkillboard.com/api/history/{$date}.json");
            $kills = $data->getBody()->getContents();

            if (!empty($kills)) {
                file_put_contents($file, $kills);
                return json_decode($kills, true);
            } else {
                throw new \RuntimeException("Result from https://zkillboard.com/api/history/{$date}.json was empty");
            }
        }
    }

    final public function handle(): void
    {
        $processed = 1;
        $totalKillmails = 0;

        $totalData = $this->fetcher->fetch('https://zkillboard.com/api/history/totals.json') ?? [];
        $totalAvailable = new Collection(json_decode($totalData->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR));


        $totalAvailable->each(function ($row) use (&$totalKillmails) {
            $totalKillmails += $row;
        });

        $this->out('Iterating over ' . count($totalAvailable) . ' individual days');

        foreach ($totalAvailable->reverse() as $date => $total) {
            $this->out("Day: {$date} | {$total} kills available");

            try {
                $kills = $this->fetchAndCacheData($date);
            } catch (\Exception $e) {
                dump($e->getMessage(), 'fetchKillmails');
                sleep(10);
                $kills = $this->fetchAndCacheData($date);
            }

            $batch = [];
            foreach ($kills as $killId => $hash) {
                if ($killId === 'day') {
                    continue;
                }

                if ($this->killmails->findOne(['killmail_id' => $killId])->isNotEmpty()) {
                    $processed++;
                    continue;
                }

                $batch[] = [
                    'killmail_id' => (int) $killId,
                    'hash' => $hash
                ];

                $processed++;
            }

            // Remove duplicates where the hash is the same (Because it seems to contain duplicates for some reason)
            $batch = collect($batch)->unique('hash')->toArray();

            $this->out("Inserting batch of " . count($batch) . " records, total processed: {$processed}/{$totalKillmails}");
            // Split the batch into smaller chunks of 1000
            $chunks = array_chunk($batch, 1000);
            foreach($chunks as $chunk) {
                $this->killmails->setData($chunk);
                $this->killmails->saveMany();
            }
        }
    }
}
