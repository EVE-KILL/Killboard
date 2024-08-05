<?php

namespace EK\Fetchers;

use EK\Http\Fetcher;

class CharacterScrape extends Fetcher
{
    protected string $bucketName = 'character_scrape';
    protected int $bucketLimit = 1;
}
