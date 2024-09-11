<?php

namespace EK\Controllers;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Database\Connection;
use EK\Redis\Redis;
use Psr\Http\Message\ResponseInterface;

class Status extends Controller
{
    public function __construct(
        protected Connection $connection,
        protected Redis $redis
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/status[/]", ["GET"], 'Get the server status')]
    public function status(): ResponseInterface
    {
        // These checks might honestly not be needed, because if redis or mongo is down - the app will not work, period.

        // Can we connect to mongodb?
        $mongoDb = $this->connection->getConnection()->selectDatabase('app')->command(['ping' => 1]);
        $mongoConnectionStatus = $mongoDb->toArray()[0]['ok'] === 1.0;

        // Can we connect to redis?
        $redisConnectionStatus = $this->redis->getClient()->ping();

        if ($mongoConnectionStatus === true && $redisConnectionStatus === true) {
            return $this->html('OK', 0, 200);
        } else {
            return $this->html('Service Unavailable', 0, 503);
        }
    }
}
