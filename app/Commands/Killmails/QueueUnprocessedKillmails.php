<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\ProcessKillmail;
use EK\Models\Killmails;
use EK\RabbitMQ\RabbitMQ;

class QueueUnprocessedKillmails extends ConsoleCommand
{
    public string $signature = 'queue:unprocessed-killmails';
    public string $description = 'Queue all unprocessed killmails for parsing';

    public function __construct(
        protected ClassLoader $autoloader,
        protected Killmails $killmails,
        protected ProcessKillmail $parseKillmailJob,
        protected RabbitMQ $rabbitMQ
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        // Use the $this->rabbitMQ->channel to check how big the killmail queue is, if it's empty we can queue more
        $queueInfo = $this->rabbitMQ->getChannel()->queue_declare('killmail', passive: true);
        $queueSize = $queueInfo[1] ?? 0;

        if ($queueSize > 0) {
            $this->logger->info('Killmail queue is not empty, skipping');
            return;
        }

        // Get the MongoDB collection from the $killmails model
        $collection = $this->killmails->collection;

        // Use a cursor to iterate over unprocessed killmails
        $cursor = $collection->find(
            ['attackers' => ['$exists' => false]],
            [
                'projection' => ['_id' => 0, 'killmail_id' => 1, 'hash' => 1],
                'noCursorTimeout' => true, // Prevent cursor timeout if processing takes a long time
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
                $this->logger->info('Queueing batch of ' . count($mailsToQueue) . ' killmails');
                $this->processKillmail->massEnqueue($mailsToQueue);
                $mailsToQueue = []; // Reset the array
            }
        }

        // Enqueue any remaining killmails
        if (count($mailsToQueue) > 0) {
            $this->logger->info('Queueing final batch of ' . count($mailsToQueue) . ' killmails');
            $this->processKillmail->massEnqueue($mailsToQueue);
        }
    }
}
