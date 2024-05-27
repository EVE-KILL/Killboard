<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\ESI\EsiFetcher;
use EK\Models\Killmails;
use EK\Models\KillmailsESI;
use EK\Redis\Redis;

class processKillmail extends Jobs
{
    protected string $defaultQueue = 'killmail';
    public function __construct(
        protected Killmails $killmails,
        protected KillmailsESI $killmailsESI,
        protected \EK\ESI\Killmails $esiKillmails,
        protected EsiFetcher $esiFetcher,
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

        // Get the killmail data from the killmail model
        $killmail = $this->killmails->findOneOrNull(['killmail_id' => $killmail_id, 'hash' => $hash]);
        if (!$killmail) {
            throw new \RuntimeException('Killmail with id: ' . $killmail_id . ' not found');
        }

        // Get the killmail data from the ESI
        $killmailData = $this->esiKillmails->getKillmail($killmail_id, $hash);

        // Insert the killmail data into the esi killmails collection
        $this->killmailsESI->setData($killmailData);
        $this->killmailsESI->save();

        // Parse the killmail
        $parsedKillmail = $this->killmailHelper->parseKillmail($killmail_id, $hash, $war_id);

        // Insert the parsed killmail into the killmails collection
        $this->killmails->setData($parsedKillmail);
        $this->killmails->save();
    }
}