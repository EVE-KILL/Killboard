<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Fetchers\zKillboard;
use EK\Jobs\processKillmail;
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
        protected zKillboard $zkbFetcher,
        protected processKillmail $processKillmail,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        ini_set('memory_limit', '-1');

        $date = new \DateTime($this->fromDate ?? 'now');
        $direction = in_array($this->direction, ['latest', 'oldest']) ? $this->direction : 'latest';

        $totalKillmails = 0;
        $killmailsProcessed = 0;

        $totalData = $this->zkbFetcher->fetch('https://zkillboard.com/api/history/totals.json');
        $totalData = json_validate($totalData['body']) ? collect(json_decode($totalData['body'], true)) : collect([]);

        $oldestDate = new \DateTime($totalData->keys()->first());
        $latestDate = new \DateTime($totalData->keys()->last());
        $totalData->each(function ($row) use (&$totalKillmails) {
            $totalKillmails += $row;
        });

        $this->out('Iterating over ' . $totalData->count() . ' individual days');

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
        $killmails = $this->zkbFetcher->fetch("https://zkillboard.com/api/history/{$date}.json");
        $killmails = json_validate($killmails['body']) ? collect(json_decode($killmails['body'], true)) : collect([]);

        $this->out("Processing {$date} with " . count($killmails) . " killmails");

        if ($killmails->count() > 0) {
            $killmailBatch = [];
            foreach ($killmails as $killmail_id => $hash) {
                // Emit the killmail to the processKillmail job as well
                $killmailBatch[] = [
                    'killmail_id' => (int)$killmail_id,
                    'hash' => $hash
                ];
            }

            $this->processKillmail->massEnqueue($killmailBatch);
            $this->killmails->setData($killmailBatch);
            $insertCount = $this->killmails->saveMany();

            $this->out("Inserted {$insertCount} killmails");
        }
    }
}
