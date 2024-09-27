<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\ProcessKillmail;
use EK\Models\Killmails;

class ParseKillmails extends ConsoleCommand
{
    public string $signature = 'parse:killmails
        { --debug : Debug the killmail by emitting it into the terminal }
        { --inline : Parse all killmails inline }
        { killid? : Parse a single killmail }';
    public string $description = 'Parse all killmails manually, in chunks of 1000 at a time (Or a single one)';

    public function __construct(
        protected ClassLoader $autoloader,
        protected killmails $killmails,
        protected \EK\Helpers\Killmails $killmailsHelper,
        protected ProcessKillmail $parseKillmailJob
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        ini_set('memory_limit', '-1');
        $killmail_id = $this->killid ?? null;
        if ($killmail_id !== null) {
            $hash = $this->killmailsHelper->getKillMailHash($killmail_id);
            $parsedKillmail = $this->killmailsHelper->parseKillmail($killmail_id, $hash);

            if ($this->debug === true) {
                echo json_encode($parsedKillmail, JSON_PRETTY_PRINT);
                die();
            }

            $this->killmails->setData($parsedKillmail);
            $this->killmails->save();
        } elseif ($this->inline) {
            $unparsedKillmails = $this->killmails->aggregate([
                ['$match' => ['kill_time' => ['$exists' => false]]],
                ['$sort' => ['killmail_id' => -1]],
                ['$project' => ['killmail_id' => 1, 'hash' => 1]],
                ['$limit' => 1000]
            ]);

            foreach ($$unparsedKillmails as $killmail) {
                $hash = $killmail['hash'];
                $startTime = microtime(true);

                $this->out('Parsing killmail ' . $killmail['killmail_id'] . ' with hash ' . $hash . '...');
                $parsedKillmail = $this->killmailsHelper->parseKillmail($killmail['killmail_id'], $hash);

                if ($this->debug === true) {
                    dd($parsedKillmail);
                }

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

            $unparsedKillmails = $this->killmails->collection->aggregate([
                ['$match' => ['killtime' => ['$exists' => false]]],
                ['$sort' => ['killmail_id' => -1]],
                ['$project' => ['killmail_id' => 1, 'hash' => 1]],
            ]);

            foreach ($unparsedKillmails as $killmail) {
                $this->parseKillmailJob->enqueue([
                    'killmail_id' => $killmail['killmail_id'],
                    'hash' => $killmail['hash']
                ]);
                $progressBar->advance();
            }
        }
    }
}
