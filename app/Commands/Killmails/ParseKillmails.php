<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\ConsoleCommand;
use EK\Models\Killmails;

class ParseKillmails extends ConsoleCommand
{
    public string $signature = 'parse:killmails
        { --debug : Debug the killmail by emitting it into the terminal }
        { --inline : Parse all killmails inline }
        { killID? : Parse a single killmail }';
    public string $description = 'Parse all killmails manually, in chunks of 1000 at a time (Or a single one)';

    public function __construct(
        protected ClassLoader $autoloader,
        protected killmails $killmails,
        protected \EK\Helpers\Killmails $killmailsHelper,
        //protected ParseKillmail $parseKillmailJob
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        ini_set('memory_limit', '-1');
        if ($this->killID !== null) {
            $hash = $this->killmailsHelper->getKillMailHash($this->killID);
            $parsedKillmail = $this->killmailsHelper->parseKillmail($this->killID, $hash);

            if ($this->debug === true) {
                dd($parsedKillmail->toArray());
            }

            $this->killmails->setData($parsedKillmail->toArray());
            $this->killmails->save();
        } elseif ($this->inline) {
            $unparsedKillmails = $this->killmails->aggregate([
                //[
                    // Fetch all the killmails where updated exists, and that are no older than 4 hours
                    //'$match' => [
                    //    '$or' => [
                    //        ['updated' => ['$exists' => true]],
                    //        ['updated' => ['$gt' => new \MongoDB\BSON\UTCDateTime(strtotime('-4 hours') * 1000)]]
                    //    ]
                    //]
                    // Get killmails that don't have updated set, or are older than 7 days
                    //'$match' => [
                    //    '$or' => [
                    //        ['updated' => ['$exists' => false]],
                    //        ['updated' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]]
                    //    ]
                    //]
                    // Get all killmails
                //],
                ['$sort' => ['killID' => -1]],
                ['$project' => ['killID' => 1]],
                ['$limit' => 100000]
            ]);

            foreach ($this->getKillmails($unparsedKillmails) as $killmail) {
                $hash = $this->killmailsHelper->getKillMailHash($killmail['killID']);
                $startTime = microtime(true);
                $this->out('Parsing killmail ' . $killmail['killID'] . ' with hash ' . $hash . '...');
                $parsedKillmail = $this->killmailsHelper->parseKillmail($killmail['killID'], $hash);

                $this->killmails->setData($parsedKillmail->toArray());
                $this->killmails->save();
                $this->out('Parsed killmail ' . $killmail['killID'] . ' in ' . round(microtime(true) - $startTime, 2) . ' seconds');
                $this->out('https://eve-kill.com/api/v1/killmail/' . $killmail['killID']);
            }
        } else {
            $this->out('Fetching unparsed killmail count...');
            // Get the count of killmails WITHOUT the updated field
            //$unparsedCount = $this->killmails->count(['updated' => ['$exists' => false]]);
            $progressBar = $this->progressBar(82000000); //$unparsedCount);
            $progressBar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%\n");
            $progressBar->setMessage('Enqueueing killmails');
            $progressBar->start();

            $unparsedKillmails = $this->killmails->aggregate([
                //[
                //    '$match' => [
                //        '$or' => [
                //            ['updated' => ['$exists' => false]],
                //            ['updated' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)]]
                //        ]
                //    ]
                //],
                ['$sort' => ['killID' => -1]],
                ['$project' => ['killID' => 1]]
            ]);

            foreach ($this->getKillmails($unparsedKillmails) as $killmail) {
                $this->parseKillmailJob->enqueue(['killID' => $killmail['killID']]);
                $progressBar->advance();
            }
        }
    }

    private function getKillmails($unparsedKillmails): \Generator
    {
        foreach ($unparsedKillmails as $killmail) {
            yield $killmail;
        }
    }
}
