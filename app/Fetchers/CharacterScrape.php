<?php

namespace EK\Fetchers;

class CharacterScrape extends ESI
{
    protected string $bucketName = 'character_scrape';
    protected int $rateLimit = 1;
}
