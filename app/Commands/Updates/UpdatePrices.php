<?php

namespace EK\Commands\Updates;

use EK\Api\ConsoleCommand;
use EK\Models\Prices;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\UnavailableStream;
use MongoDB\BSON\UTCDateTime;

class UpdatePrices extends ConsoleCommand
{
    protected string $signature = 'update:prices
        { --historic : Gets _ALL_ Prices going back to 2016 from the Historic everef dataset }
        { --debug : Display error messages }
    ';
    protected string $description = 'Updates the item prices';
    protected int $errorCount = 0;

    public function __construct(
        protected Prices $prices
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

        // Ensure indexes are set on the model before proceeding
        $this->prices->ensureIndexes();

        if ($this->historic === true) {
            $this->historicData();
            exit(0);
        } else {
            $this->marketData();
        }
    }

    /**
     * @throws UnavailableStream
     * @throws Exception
     */
    protected function marketData(): void
    {
        // Fetch the latest prices from the EVE market using data.everef.net
        // We can only get the data from yesterday
        $yesterday = date('Y-m-d', strtotime('yesterday'));
        $url = "https://data.everef.net/market-history/" . date('Y') . "/market-history-{$yesterday}.csv.bz2";
        $cachePath = BASE_DIR . '/resources/cache';

        // Download the file
        $this->out("Downloading {$yesterday}");
        exec("curl --progress-bar -o {$cachePath}/{$yesterday}.csv.bz2 {$url}");
        exec("bzip2 -d {$cachePath}/{$yesterday}.csv.bz2");

        // Import the file
        $reader = Reader::createFromPath("{$cachePath}/{$yesterday}.csv");
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();

        $bigInsert = $this->getBigInsert($records);

        $this->insertMarketData($bigInsert);
    }

    /**
     * @throws UnavailableStream
     * @throws Exception
     */
    protected function historicData(): void
    {
        $currentYear = date('Y');
        $startYear = 2007;
        $historyUrl = 'https://data.everef.net/market-history';

        $cachePath = BASE_DIR . '/resources/cache';

        // Download all the historic data by date into markethistory
        $this->out('<info>Downloading Historic Data by Date</info>');
        foreach(range($startYear, $currentYear) as $year) {
            $startDate = "{$year}-01-01";
            $daysInAYear = 364;
            $increments = 0;
            $baseUrl = "{$historyUrl}/{$year}";
            // If the year is 2007, we need to start from the 5th December
            if ($year === 2007) {
                $startDate = "2007-12-05";
                $daysInAYear = 26;
            }

            exec("mkdir -p {$cachePath}/markethistory/{$year}");

            do {
                $currentDate = date('Y-m-d', strtotime($startDate . ' + ' . $increments . ' days'));
                // If the $currentDate is the same as the actual current date('Y-m-d H:i:s') then we can stop
                if ($currentDate === date('Y-m-d')) {
                    break;
                }

                // Check if file exists locally already
                if (file_exists("{$cachePath}/markethistory/{$year}/market-history-{$currentDate}.csv")) {
                    continue;
                }

                // If the bz2 file exists, we can just decompress it
                if (file_exists("{$cachePath}/markethistory/{$year}/market-history-{$currentDate}.csv.bz2")) {
                    exec("bzip2 -d {$cachePath}/markethistory/{$year}/market-history-{$currentDate}.csv.bz2");
                    continue;
                } else {
                    $this->out("Downloading {$currentDate}");
                    $this->out("{$baseUrl}/market-history-{$currentDate}.csv.bz2");
                    exec("curl --progress-bar -o {$cachePath}/markethistory/{$year}/market-history-{$currentDate}.csv.bz2 {$baseUrl}/market-history-{$currentDate}.csv.bz2");
                    exec("bzip2 -d {$cachePath}/markethistory/{$year}/market-history-{$currentDate}.csv.bz2");
                }
            } while($increments++ < $daysInAYear && $currentDate !== date('Y-m-d'));
        }

        // Now that it's all downloaded, we can import it
        $this->out('<info>Importing Historic Data</info>');
        $years = range($startYear, $currentYear);

        foreach ($years as $year) {
            $this->out("Importing {$year}");
            $csvs = glob("{$cachePath}/markethistory/{$year}/*.csv");
            $insertCount = 0;

            foreach ($csvs as $csv) {
                $reader = Reader::createFromPath($csv);
                $reader->setHeaderOffset(0);
                $records = $reader->getRecords();
                $insertCount += $this->insertMarketData($this->getBigInsert($records));
            }

            $this->out("Inserted {$insertCount} records for {$year}");

            if ($this->errorCount > 0 && !$this->debug) {
                $this->out("<error>Errors: {$this->errorCount}</error>");
            }
        }
    }

    protected function insertMarketData(\Generator $data): int
    {
        $batchSize = 10000;
        $batch = [];
        $index = 0;
        $totalCount = 0;

        foreach ($data as $record) {
            $batch[] = $record;
            if (count($batch) === $batchSize) {
                $totalCount += $this->insertBatch($batch, $index);
                $batch = [];
                $index += $batchSize;
            }
        }

        // Insert the remaining records
        if (!empty($batch)) {
            $totalCount += $this->insertBatch($batch, $index);
        }

        return $totalCount;
    }

    private function insertBatch(array $batch, int $index): int
    {
        $insertCount = 0;
        try {
            $this->prices->collection->insertMany($batch, ['upsert' => true]);
            $insertCount = count($batch);
        } catch (\Exception $e) {
            $this->errorCount++;
            if ($this->debug) {
                fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            }
            // Insert the batch one by one and catch any errors
            foreach ($batch as $record) {
                try {
                    $this->prices->collection->insertOne($record, ['upsert' => true]);
                    $insertCount++;
                } catch (\Exception $e) {
                    $this->errorCount++;
                    if ($this->debug) {
                        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
                    }
                }
            }
        }
        return $insertCount;
    }

    /**
     * @param \Iterator $records
     * @return array
     */
    protected function getBigInsert(\Iterator $records): \Generator
    {
        foreach ($records as $record) {
            // Only add the record if the region_id is The Forge
            if ((int) $record['region_id'] === 10000002) {
                yield [
                    'typeID' => (int)$record['type_id'],
                    'average' => (float)$record['average'],
                    'highest' => (float)$record['highest'],
                    'lowest' => (float)$record['lowest'],
                    'regionID' => (int)$record['region_id'],
                    'date' => new UTCDateTime(strtotime($record['date']) * 1000),
                ];
            }
        }
    }
}
