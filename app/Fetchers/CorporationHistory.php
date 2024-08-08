<?php

namespace EK\Fetchers;

class CorporationHistory extends ESI
{
    protected string $bucketName = 'esi_corporation_history';
    protected int $rateLimit = 5;
}
