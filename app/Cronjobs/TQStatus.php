<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Cache\Cache;
use EK\Fetchers\ESI;
use EK\Logger\StdOutLogger;

class TQStatus extends Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected ESI $esi,
        protected Cache $cache,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        // Get the status of TQ
        $result = $this->esi->fetch('/latest/status', ignorePause: true);
        $status = $result['status'];
        $response = json_decode($result['body'], true);
        $status = $result['status'];

        if (isset($response['error'])) {
            switch($response['error']) {
                case 'Timeout contacting tranquility':
                    $this->cache->set('tq_status', 'offline');
                    $this->cache->set('fetcher_paused', 60);
                    break;
                default:
                    $this->cache->set('tq_status', 'unknown');
                    $this->cache->set('fetcher_paused', 300);
                    break;
            }
        }

        switch($status) {
            case 503:
                $this->cache->set('tq_status', 'offline');
                $this->cache->set('fetcher_paused', 60);
                break;
            default:
                $this->cache->set('tq_status', 'online');
                $this->cache->set('tq_players', $response['players']);
                $this->cache->set('tq_start_time', $response['start_time']);
                $this->cache->set('tq_server_version', $response['server_version']);
                $this->cache->remove('fetcher_paused');
                break;
        }
    }
}
