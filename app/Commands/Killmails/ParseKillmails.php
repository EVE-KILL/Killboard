<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Killmails;

class ParseKillmails extends ConsoleCommand
{
    public string $signature = 'parse:killmails
        { --debug : Debug the killmail by emitting it into the terminal }
        { --inline : Parse all killmails inline }
        { killmail_id? : Parse a single killmail }';
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
        if ($this->killmail_id !== null) {
            $hash = $this->killmailsHelper->getKillMailHash($this->killmail_id);
            $parsedKillmail = $this->killmailsHelper->parseKillmail($this->killmail_id, $hash);

            if ($this->debug === true) {
                dd($parsedKillmail->toArray());
            }

            $this->killmails->setData($parsedKillmail->toArray());
            $this->killmails->save();
        } elseif ($this->inline) {
            $unparsedKillmails = $this->killmails->aggregate([
                ['$match' => ['kill_time' => ['$exists' => false]]],
                ['$sort' => ['killmail_id' => -1]],
                ['$project' => ['killmail_id' => 1, 'hash' => 1]],
                ['$limit' => 1]
            ]);

            foreach ($this->getKillmails($unparsedKillmails) as $killmail) {
                $hash = $killmail['hash'];
                $startTime = microtime(true);

                $this->out('Parsing killmail ' . $killmail['killmail_id'] . ' with hash ' . $hash . '...');
                $parsedKillmail = $this->killmailsHelper->parseKillmail($killmail['killmail_id'], $hash);

                $this->killmails->setData($parsedKillmail);
                $this->killmails->save();
                $this->out('Parsed killmail ' . $killmail['killmail_id'] . ' in ' . round(microtime(true) - $startTime, 2) . ' seconds');
                $this->out('https://eve-kill.com/api/v1/killmail/' . $killmail['killmail_id']);
            }
        } else {
            $this->out('Fetching unparsed killmail count...');
            // Get the count of killmails WITHOUT the updated field
            $unparsedCount = $this->killmails->count(['killtime' => ['$exists' => false]]);
            $progressBar = $this->progressBar($unparsedCount);
            $progressBar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%% - Estimated time remaining: %estimated%\n");
            $progressBar->setMessage('Enqueueing killmails');
            $progressBar->start();

            $unparsedKillmails = $this->killmails->aggregate([
                ['$sort' => ['killmail_id' => -1]],
                ['$project' => ['killmail_id' => 1]]
            ]);

            foreach ($this->getKillmails($unparsedKillmails) as $killmail) {
                $this->parseKillmailJob->enqueue(['killmail_id' => $killmail['killmail_id']]);
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