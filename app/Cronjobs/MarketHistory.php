<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Models\Prices;

class MarketHistory extends Cronjob
{
    protected string $cronTime = '0 */12 * * *';

    public function __construct(
        protected Prices $prices,
        protected \EK\Helpers\MarketHistory $marketHistory,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $oldestDate = $this->prices->findOne(['date' => ['$exists' => true]], ['sort' => ['date' => -1]])->get('date')->toDateTime();
        $daysSinceOldestDate = (new \DateTime())->diff($oldestDate)->days;
        $minDays = 7;

        $daysToFetch = $daysSinceOldestDate < $minDays ? $minDays : $daysSinceOldestDate;

        for ($i = 0; $i < $daysToFetch; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $year = date('Y', strtotime($date));

            $records = $this->marketHistory->getMarketHistory($date);
            if (!$records) {
                continue;
            }
            $generator = $this->marketHistory->generateData($records);
            $this->marketHistory->insertData($generator);
            $this->logger->info("Inserted market history data for $date");
        }
    }
}
