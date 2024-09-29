<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Fetchers\zKillboard;
use EK\Jobs\ProcessKillmail;
use EK\Models\Killmails;

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
        protected ProcessKillmail $processKillmail,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        ini_set('memory_limit', '-1');

        $date = new \DateTime($this->fromDate ?? 'now');
        $direction = in_array($this->direction, ['latest', 'oldest']) ? $this->direction : 'latest';

        $totalData = $this->zkbFetcher->fetch('https://zkillboard.com/api/history/totals.json');
        $totalData = json_validate($totalData['body']) ? json_decode($totalData['body'], true) : [];

        $oldestDate = new \DateTime(array_key_first($totalData));
        $latestDate = new \DateTime(array_key_last($totalData));
        $totalKillmails = array_sum($totalData);

        $this->out('Iterating over ' . count($totalData) . ' individual days');

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
        $killmails = json_validate($killmails['body']) ? json_decode($killmails['body'], true) : [];

        $this->out("Processing {$date} with " . count($killmails) . " killmails");

        if (!empty($killmails)) {
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
