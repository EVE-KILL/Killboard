<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Logger\Logger;
use EK\Models\Wars;
use EK\RabbitMQ\RabbitMQ;
use MongoDB\BSON\UTCDateTime;

class ProcessWar extends Jobs
{
    protected string $defaultQueue = 'low';
    public function __construct(
        protected Wars $warsModel,
        protected \EK\ESI\Wars $esiWars,
        protected ProcessKillmail $killmailJob,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $war_id = $data['war_id'];
        $warData = $this->esiWars->getWar($war_id);
        $warKills = $this->esiWars->getWarKills($war_id);

        $warData['kills'] = count($warKills);

        // Sort out all the timestamps
        if (isset($warData['started'])) {
            $warData['started'] = new UTCDateTime(strtotime($warData['started']) * 1000);
        }
        if (isset($warData['finished'])) {
            $warData['finished'] = new UTCDateTime(strtotime($warData['finished']) * 1000);
        }
        if (isset($warData['retracted'])) {
            $warData['retracted'] = new UTCDateTime(strtotime($warData['retracted']) * 1000);
        }
        if (isset($warData['declared'])) {
            $warData['declared'] = new UTCDateTime(strtotime($warData['declared']) * 1000);
        }

        $this->warsModel->setData($warData);
        $this->warsModel->save();

        foreach ($warKills as $kill) {
            $this->killmailJob->enqueue(['killmail_id' => $kill['killmail_id'], 'hash' => $kill['killmail_hash'], 'war_id' => $war_id]);
        }
    }
}
