<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\ProcessKillmail;
use EK\Models\Killmails;

class QueueUnprocessedKillmails extends ConsoleCommand
{
    public string $signature = 'queue:unprocessed-killmails';
    public string $description = 'Queue all unprocessed killmails for parsing';

    public function __construct(
        protected ClassLoader $autoloader,
        protected Killmails $killmails,
        protected ProcessKillmail $processKillmail
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $cursor = $this->killmails->collection->find(
            ['attackers' => ['$exists' => false]],
            [
                'projection' => ['_id' => 0, 'killmail_id' => 1, 'hash' => 1],
                'noCursorTimeout' => true, // Prevent cursor timeout
            ]
        );

        $mailsToQueue = [];
        foreach ($cursor as $killmail) {
            $mailsToQueue[] = [
                'killmail_id' => $killmail['killmail_id'],
                'hash' => $killmail['hash']
            ];

            // Periodically enqueue to prevent memory exhaustion
            if (count($mailsToQueue) >= 10000) {
                $this->out('Queueing batch of ' . count($mailsToQueue) . ' killmails');
                $this->processKillmail->massEnqueue($mailsToQueue);
                $mailsToQueue = [];
            }
        }

        // Enqueue any remaining killmails
        if (count($mailsToQueue) > 0) {
            $this->out('Queueing final batch of ' . count($mailsToQueue) . ' killmails');
            $this->processKillmail->massEnqueue($mailsToQueue);
        }
    }
}
