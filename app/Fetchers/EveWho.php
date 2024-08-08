<?php

namespace EK\Fetchers;

use EK\Http\Fetcher;

class EveWho extends Fetcher
{
    protected string $baseUri = 'https://evewho.com/';
    protected string $bucketName = 'eve_who';
    protected bool $useThrottle = true;
    protected int $bucketLimit = 5;
    protected int $timeout = 60;
}
