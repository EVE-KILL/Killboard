<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Models\Killmails;
use EK\Redis\Redis;

class processKillmail extends Jobs
{
    protected string $defaultQueue = 'killmail';
    public function __construct(
        protected Killmails $killmails,
        protected \EK\Helpers\Killmails $killmailHelper,
        protected Redis $redis
    ) {
        parent::__construct($redis);
    }

    public function handle(array $data): void
    {
        $killmail_id = $data['killmail_id'];
        $hash = $data['hash'];
        $war_id = $data['war_id'] ?? 0;

        // Parse the killmail
        $parsedKillmail = $this->killmailHelper->parseKillmail($killmail_id, $hash, $war_id);

        // Insert the parsed killmail into the killmails collection
        $this->killmails->setData($parsedKillmail);
        $this->killmails->save();
    }
}