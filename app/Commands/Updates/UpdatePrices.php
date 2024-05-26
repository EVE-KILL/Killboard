<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\MarketHistory;
use EK\Models\Prices;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\UnavailableStream;
use MongoDB\BSON\UTCDateTime;

class UpdatePrices extends ConsoleCommand
{
    protected string $signature = 'update:prices
        { --startFrom= : The date to start fetching prices from }
        { --reverse : Reverse the order of the price fetching }
        { --days=7 : The number of days to fetch prices for }
        { --historic : Gets _ALL_ Prices going back to 2016 from the Historic everef dataset }
        { --debug : Display error messages }
    ';
    protected string $description = 'Updates the item prices';

    public function __construct(
        protected MarketHistory $marketHistory,
    ) {
        parent::__construct();
    }

    /**
     * @throws UnavailableStream
     * @throws Exception
     */
    final public function handle(): void
    {
        ini_set('memory_limit', '-1');

        $daysToFetch = $this->days ?? 7;
        $historicFetch = $this->historic ?? false;
        $oldestToNewest = $this->reverse ?? false;
        $startFrom = $this->startFrom ?? null;

        if ($historicFetch) {
            $this->out('Fetching historic prices...');
            $this->fetchHistoricPrices($oldestToNewest, $startFrom);
        } else {
            $this->out('Fetching prices for the last ' . $daysToFetch . ' days...');
            $this->fetchPrices($daysToFetch);
        }
    }

    private function fetchPrices(int $daysToFetch): void
    {
        for ($i = 0; $i < $daysToFetch; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $this->processDate($date);
        }
    }

    private function fetchHistoricPrices(bool $oldestToNewest, ?string $startFrom): void
    {
        // The earliest date we have market history for
        $earliestMarketHistory = $startFrom !== null ? new \DateTime($startFrom) : new \DateTime('2003-10-01');
        $currentDate = new \DateTime();
        $daysSinceOldestDate = $currentDate->diff($earliestMarketHistory)->days;

        if ($oldestToNewest) {
            for ($i = 0; $i <= $daysSinceOldestDate; $i++) {
                $date = $earliestMarketHistory->modify('+1 day')->format('Y-m-d');
                $this->processDate($date);
            }
        } else {
            for ($i = 0; $i <= $daysSinceOldestDate; $i++) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $this->processDate($date);
            }
        }
    }

    private function processDate(string $date): void
    {
        $records = $this->marketHistory->getMarketHistory($date);
        if ($records !== null) {
            $this->out('Inserting prices for ' . $date);
            $generator = $this->marketHistory->generateData($records);
            $insertCount = $this->marketHistory->insertData($generator);
            $this->out('Inserted ' . $insertCount . ' prices for ' . $date);
        }
    }
}
