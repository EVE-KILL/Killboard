<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\ProcessKillmail;
use EK\Logger\StdOutLogger;
use EK\Models\Killmails;
use EK\RabbitMQ\RabbitMQ;

class QueueUnprocessedKillmails extends Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected Killmails $killmails,
        protected ProcessKillmail $processKillmail,
        protected RabbitMQ $rabbitMQ,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
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
                'projection' => ['_id' => 0, 'killmail_id' => 1, 'hash' => 1],
                'limit' => 1000000
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
