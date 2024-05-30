<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Http\Fetcher;
use EK\Models\Killmails;
use Illuminate\Support\Collection;

class FetchKillmails extends ConsoleCommand
{
    public string $signature = 'fetch:killmails
        { --fromDate= : The date to start fetching from }
        { --direction=latest : The direction to fetch from (latest or oldest) }
    ';
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

            do {
                $data = $this->fetcher->fetch("https://zkillboard.com/api/history/{$date}.json");
            } while (!in_array($data->getStatusCode(), [200, 304]));

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
        ini_set('memory_limit', '-1');

        // Enable garbage collection
        gc_enable();

        $date = new \DateTime($this->fromDate ?? 'now');
        $direction = in_array($this->direction, ['latest', 'oldest']) ? $this->direction : 'latest';

        $totalKillmails = 0;
        $killmailsProcessed = 0;

        $totalData = $this->fetcher->fetch('https://zkillboard.com/api/history/totals.json') ?? [];
        $totalAvailable = new Collection(json_decode($totalData->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR));
        $oldestDate = new \DateTime($totalAvailable->keys()->first());
        $latestDate = new \DateTime($totalAvailable->keys()->last());
        $totalAvailable->each(function ($row) use (&$totalKillmails) {
            $totalKillmails += $row;
        });

        $this->out('Iterating over ' . count($totalAvailable) . ' individual days');

        // Iterate over all days from $date either going forward to the latest date from $totalData, or going backwards to the oldest date from $totalData
        while(true) {
            $this->processKillmails($date);

            if ($direction === 'latest') {
                $date->modify('-1 day');
                if ($date < $oldestDate) {
                    break;
                }
            } else {
                $date->modify('+1 day');
                if ($date > $latestDate) {
                    break;
                }
            }

            gc_collect_cycles();
        }

    }

    private function processKillmails(\DateTime $date): void
    {
        $date = $date->format('Ymd');
        $killmails = $this->fetchAndCacheData($date);
        $this->out("Processing {$date} with " . count($killmails) . " killmails");

        $killmailBatch = [];
        foreach ($killmails as $killmail_id => $hash) {
            $killmailBatch[] = [
                'killmail_id' => (int) $killmail_id,
                'hash' => $hash
            ];
        }

        $this->killmails->setData($killmailBatch);
        $insertCount = $this->killmails->saveMany();

        $this->out("Inserted {$insertCount} killmails");
    }
}
