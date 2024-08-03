<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Models\Wars;
use EK\Redis\Redis;
use MongoDB\BSON\UTCDateTime;

class ProcessWar extends Jobs
{
    protected string $defaultQueue = 'low';
    public function __construct(
        protected Wars $warsModel,
        protected \EK\ESI\Wars $esiWars,
        protected ProcessKillmail $killmailJob,
        protected Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $war_id = $data['war_id'];
        $warData = $this->esiWars->getWar($war_id);

        $war = json_decode($warData['body'], true);

        $warKills = $this->esiWars->getWarKills($war_id);
        $kills = json_decode($warKills['body'], true);

        $war['kills'] = count($kills);

        // Sort out all the timestamps
        if (isset($war['started'])) {
            $war['started'] = new UTCDateTime(strtotime($war['started']) * 1000);
        }
        if (isset($war['finished'])) {
            $war['finished'] = new UTCDateTime(strtotime($war['finished']) * 1000);
        }
        if (isset($war['retracted'])) {
            $war['retracted'] = new UTCDateTime(strtotime($war['retracted']) * 1000);
        }
        if (isset($war['declared'])) {
            $war['declared'] = new UTCDateTime(strtotime($war['declared']) * 1000);
        }

        $this->warsModel->setData($war);
        $this->warsModel->save();

        foreach ($kills as $kill) {
            $this->killmailJob->enqueue(['killmail_id' => $kill['killmail_id'], 'hash' => $kill['killmail_hash'], 'war_id' => $war_id]);
        }
    }
}
