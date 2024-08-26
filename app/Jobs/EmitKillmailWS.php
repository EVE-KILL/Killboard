<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Config\Config;
use EK\Helpers\Killmails as HelpersKillmails;
use EK\Models\Killmails;
use EK\RabbitMQ\RabbitMQ;
use MongoDB\BSON\UTCDateTime;
use WebSocket\Client;

class EmitKillmailWS extends Jobs
{
    protected string $defaultQueue = 'websocket';
    public function __construct(
        protected Killmails $killmails,
        protected HelpersKillmails $killmailHelper,
        protected RabbitMQ $rabbitMQ,
        protected Config $config
    ) {
        parent::__construct($rabbitMQ);
    }

    public function handle(array $data): void
    {
        $client = new Client('wss://ws.eve-kill.com/kills');
        $client->text(json_encode([
            'type' => 'broadcast',
            'token' => $this->config->get('ws_token'),
            'data' => $this->cleanupTimestamps($data)
        ]));
        $client->close();
    }

    private function cleanupTimestamps(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof UTCDateTime) {
                $data[$key] = $value->toDateTime()->format('Y-m-d H:i:s');
            }

            // Sometimes we don't get an array with proper instances, sometimes we get an array with $date and $numberLong nested under each other
            if (is_array($value)) {
                if (isset($value['$date']['$numberLong']) && is_array($value['$date'])) {
                    $data[$key] = (new UTCDateTime($value['$date']['$numberLong']))->toDateTime()->format('Y-m-d H:i:s');
                }
            }
        }

        return $data;
    }
}
