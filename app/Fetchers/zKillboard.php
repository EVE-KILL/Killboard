<?php

namespace EK\Fetchers;

use EK\Http\Fetcher;

class zKillboard extends Fetcher
{
    protected string $baseUri = 'https://zkillboard.com/api/';
    protected string $userAgent = 'EK/1.0';
    protected string $bucketName = 'zkb';
    protected bool $useProxy = false;
    protected int $rateLimit = 50;
    protected int $timeout = 60;
}
