<?php

namespace EK\Fetchers;

class History extends ESI
{
    protected string $bucketName = 'esi_history';
    protected int $rateLimit = 30;
}
