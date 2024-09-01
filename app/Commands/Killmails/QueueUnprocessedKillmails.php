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
        protected killmails $killmails,
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

        // Find killmails that haven't been processed, meaning no $attackers or $victim array
        $unprocessedKillmails = $this->killmails->find(
            ['attackers' => ['$exists' => false]],
            [
                'projection' => ['_id' => 0, 'killmail_id' => 1, 'hash' => 1]
            ]
        );

        $mailsToQueue = [];
        foreach($unprocessedKillmails as $killmail) {
            $mailsToQueue[] = [
                'killmail_id' => $killmail['killmail_id'],
                'hash' => $killmail['hash']
            ];
        }

        $this->logger->info('Queueing ' . count($mailsToQueue) . ' unprocessed killmails');

        $this->processKillmail->massEnqueue($mailsToQueue);
    }
}
