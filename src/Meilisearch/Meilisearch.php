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
        $span = $this->startSpan('meilisearch.addDocuments', [
            'indexName' => $indexName,
            'documentsCount' => count($documents),
        ]);

        try {
            $index = $this->client->index($indexName);
            $index->addDocuments($documents);
        } catch (\Throwable $e) {
            SentrySdk::getCurrentHub()->captureException($e);
            throw $e;
        } finally {
            $span->finish();
        }
    }

    public function search(string $query, string $indexName = 'search'): SearchResult
    {
        $span = $this->startSpan('meilisearch.search', [
            'indexName' => $indexName,
            'query' => $query,
        ]);

        try {
            $index = $this->client->index($indexName);
            $result = $index->search($query);
        } catch (\Throwable $e) {
            SentrySdk::getCurrentHub()->captureException($e);
            throw $e;
        } finally {
            $span->finish();
        }

        return $result;
    }

    public function clearIndex(string $indexName = 'search'): void
    {
        $span = $this->startSpan('meilisearch.clearIndex', [
            'indexName' => $indexName,
        ]);

        try {
            $index = $this->client->index($indexName);
            $index->deleteAllDocuments();
        } catch (\Throwable $e) {
            SentrySdk::getCurrentHub()->captureException($e);
            throw $e;
        } finally {
            $span->finish();
        }
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
