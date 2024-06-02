<?php

namespace EK\Meilisearch;

use EK\Config\Config;
use Meilisearch\Client;

class Meilisearch
{
    public Client $client;

    public function __construct(
        protected Config $config
    ) {
        $this->client = new Client('http://' . $this->config->get('meilisearch/host', 'meilisearch'));
    }

    public function addDocuments(array $documents, string $indexName = 'search'): void
    {
        $index = $this->client->index($indexName);
        $index->addDocuments($documents);
    }

    public function search(string $query, string $indexName = 'search'): array
    {
        $index = $this->client->index($indexName);
        return $index->search($query);
    }
}