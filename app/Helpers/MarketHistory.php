<?php

namespace EK\Helpers;

use EK\Models\Prices;
use League\Csv\Reader;
use MongoDB\BSON\UTCDateTime;

class MarketHistory
{
    private const int INSERT_AT_COUNT = 5000;

    public function __construct(
        protected Prices $prices
    ) {

    }

    public function getMarketHistory(string $date): ?\Iterator
    {
        $year = date('Y', strtotime($date));
        $url = "https://data.everef.net/market-history/" . $year . "/market-history-{$date}.csv.bz2";

        // Check the file exists on the server before proceeding
        $headers = get_headers($url);
        if (!str_contains($headers[0], '200')) {
            return null;
        }

        // Download the file
        $tempFile = $this->downloadFile($url);

        // Open the remote bz2 file as a stream
        $bzStream = bzopen($tempFile, 'r');

        // Read and decompress
        $csvData = '';
        while(!feof($bzStream)) {
            $csvData .= bzread($bzStream, 4096);
        }

        bzclose($bzStream);

        // Read the CSV data
        $reader = Reader::createFromString($csvData);
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();

        return $records;
    }

    private function downloadFile(string $url): string
    {
        $curl = curl_init($url);
        $tempFile = tempnam(sys_get_temp_dir(), 'market-history');
        $fp = fopen($tempFile, 'w');
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_exec($curl);
        curl_close($curl);
        fclose($fp);

        return $tempFile;
    }

    public function generateData(\Iterator $marketHistory): array
    {
        $bigInsert = [];
        foreach($marketHistory as $record) {
            $bigInsert[] = [
                'type_id' => (int) $record['type_id'],
                'average' => (float) $record['average'],
                'highest' => (float) $record['highest'],
                'lowest' => (float) $record['lowest'],
                'region_id' => (int) $record['region_id'],
                'order_count' => (int) $record['order_count'] ?? 0,
                'volume' => (int) $record['volume'] ?? 0,
                'date' => new UTCDateTime(strtotime($record['date']) * 1000),
            ];
        }

        return $bigInsert;
    }

    public function insertData(array $marketHistory): int
    {
        $this->prices->setData($marketHistory);
        return $this->prices->saveMany();
    }
}