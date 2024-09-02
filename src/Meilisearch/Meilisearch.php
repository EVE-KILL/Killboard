<?php

namespace EK\Meilisearch;

use EK\Config\Config;
use Meilisearch\Client;
use Meilisearch\Search\SearchResult;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;

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
        $span = $this->startSpan('meilisearch.addDocuments', compact('indexName', 'documentsCount'));

        $index = $this->client->index($indexName);
        $index->addDocuments($documents);

        $span->finish();
    }

    public function search(string $query, string $indexName = 'search'): SearchResult
    {
        $span = $this->startSpan('meilisearch.search', compact('indexName', 'query'));

        $index = $this->client->index($indexName);
        $result = $index->search($query);

        $span->finish();

        return $result;
    }

    public function clearIndex(string $indexName = 'search'): void
    {
        $span = $this->startSpan('meilisearch.clearIndex', compact('indexName'));

        $index = $this->client->index($indexName);
        $index->deleteAllDocuments();

        $span->finish();
    }

    protected function startSpan(string $operation, array $data = []): \Sentry\Tracing\Span
    {
        $spanContext = new SpanContext();
        $spanContext->setOp($operation);
        $spanContext->setData($data);

        $span = SentrySdk::getCurrentHub()->getSpan()?->startChild($spanContext);
        return $span ?: SentrySdk::getCurrentHub()->getTransaction()->startChild($spanContext);
    }
}
