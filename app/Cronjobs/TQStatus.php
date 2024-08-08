<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Cache\Cache;
use EK\Http\Fetcher;
use EK\Models\Prices;

class TQStatus extends Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected Fetcher $fetcher,
        protected Cache $cache,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // Get the status of TQ
        $result = $this->fetcher->fetch('https://esi.evetech.net/latest/status/');
        $status = json_decode($result['body'], true);

        if (isset($status['error'])) {
            switch($status['error']) {
                case 'Timeout contacting tranquility':
                    $this->cache->set('fetcher_paused', 60);
                    break;
                default:
                    $this->cache->set('fetcher_paused', 300);
                    break;
            }
        }

        // Else update the player count, and whatnots
        // @TODO
    }
}
