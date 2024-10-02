<?php

namespace EK\Fetchers;

class History extends ESI
{
    protected string $bucketName = 'esi_history';

    // Rate limit the character/*/corporationhistory and corporation/*/alliancehistory endpoints to 20 requests per second
    protected int $rateLimit = 30;
}
