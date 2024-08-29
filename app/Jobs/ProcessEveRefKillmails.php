<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Logger\Logger;
use EK\Models\KillmailsESI;
use EK\RabbitMQ\RabbitMQ;
use MongoDB\BSON\UTCDateTime;

class ProcessEveRefKillmails extends Jobs
{
    protected string $defaultQueue = 'low';

    public function __construct(
        protected KillmailsESI $killmailsESI,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        ini_set('memory_limit', '-1');
        $url = $data['url'];

        // Get the filename from the url
        $fileName = basename($url);
        $filePath = "/tmp/{$fileName}";
        $extractPath = '/tmp/' . str_replace('.tar.bz2', '', $fileName);

        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        // Download the file
        file_put_contents($filePath, file_get_contents($url));

        // Unpack it
        shell_exec("tar -xjf {$filePath} -C {$extractPath}");

        // For each .json in $cacheDir/killmails
        $files = glob('/tmp/' . str_replace('.tar.bz2', '', $fileName) . '/killmails/*.json');
        $killmails = [];

        foreach ($files as $file) {
            $killmail = json_decode(file_get_contents($file), true);
            // Change a bunch of fields
            $killmail['last_modified'] = new UTCDateTime(strtotime($killmail['http_last_modified']) * 1000);
            unset($killmail['http_last_modified']);
            $killmail['killmail_time_str'] = $killmail['killmail_time'];
            $killmail['killmail_time'] = new UTCDateTime(strtotime($killmail['killmail_time']) * 1000);

            // Sort the killmail fields
            ksort($killmail);

            $killmails[] = $killmail;
        }

        // Insert the killmails into the db
        $this->killmailsESI->setDataMany($killmails);
        $this->killmailsESI->saveMany();

        // Clean up the bz2
        unlink($filePath);

        // Clean up the extracted files
        shell_exec("rm -rf {$extractPath}");
    }
}
