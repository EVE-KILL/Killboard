<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Models\Killmails;
use EK\Redis\Redis;

class ProcessKillmail extends Jobs
{
    protected string $defaultQueue = 'killmail';
    public function __construct(
        protected Killmails $killmails,
        protected \EK\Helpers\Killmails $killmailHelper,
        protected EmitKillmailWS $emitKillmailWS,
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

        // Load the killmail from the collection
        $loadedKillmail = $this->killmails->find(['killmail_id' => $killmail_id]);
        dump($loadedKillmail->get('emitted'));
        if ($loadedKillmail->get('emitted') === true) {
            return;
        }

        // Enqueue the killmail into the websocket emitter
        $this->emitKillmailWS->enqueue($parsedKillmail);
        // Update the emitted field to ensure we don't emit the killmail again
        $this->killmails->collection->updateOne(
            ['killmail_id' => $killmail_id],
            ['$set' => ['emitted' => true]]
        );
    }
}
