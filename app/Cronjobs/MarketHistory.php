<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Logger\Logger;
use EK\Models\Prices;
use League\Csv\Reader;
use MongoDB\BSON\UTCDateTime;

class MarketHistory extends Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected Prices $prices,
        protected \EK\Helpers\MarketHistory $marketHistory,
        protected Logger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $oldestDate = $this->prices->findOne(['date' => ['$exists' => true]], ['sort' => ['date' => -1]])->get('date')->toDateTime();
        $daysSinceOldestDate = (new \DateTime())->diff($oldestDate)->days;
        $minDays = 14;

        $daysToFetch = $daysSinceOldestDate < $minDays ? $minDays : $daysSinceOldestDate;

        for ($i = 0; $i < $daysToFetch; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $year = date('Y', strtotime($date));

            $records = $this->marketHistory->getMarketHistory($date);
            $generator = $this->marketHistory->generateData($records);
            $this->marketHistory->insertData($generator);
        }
    }
}