<?php

namespace EK\Fetchers;

use bandwidthThrottle\tokenBucket\BlockingConsumer;
use EK\Http\Fetcher;
use EK\Logger\FileLogger;
use Psr\Http\Message\ResponseInterface;

class EveWho extends Fetcher
{
    protected string $baseUri = 'https://evewho.com/';
    protected string $userAgent = 'EK/1.0';
    protected string $bucketName = 'eve_who';
    protected bool $useProxy = false;
    protected bool $useThrottle = true;
    protected int $bucketLimit = 5;
    protected int $timeout = 60;
    protected BlockingConsumer $throttleBucket;
    protected FileLogger $logger;
}