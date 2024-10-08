<?php

namespace EK\Cache;

use EK\Redis\Redis;
use Redis as PhpRedis;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;

class Cache
{
    protected PhpRedis $client;

    public function __construct(
        protected Redis $redis
    ) {
        $this->client = $this->redis->getClient();
    }

    public function generateKey(...$args): string
    {
        return md5(serialize($args));
    }

    public function getTTL(string $key): int
    {
        $span = $this->startSpan('cache.get_ttl', ['key' => $key]);
        $ttl = $this->client->ttl($key);
        $span->finish();

        return $ttl;
    }

    public function get(string $key): mixed
    {
        $span = $this->startSpan('cache.get', ['key' => $key]);

        $value = $this->client->get($key);
        if ($value === null) {
            $span->setData(['cache.hit' => false]);
            $span->finish();
            return null;
        }

        $decodedValue = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $span->setData(['cache.json_error' => json_last_error_msg()]);
            $span->finish();
            return null;
        }

        $span->setData(['cache.hit' => true, 'cache.item_size' => strlen($value)]);
        $span->finish();
        return $decodedValue;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $span = $this->startSpan('cache.put', ['key' => $key, 'ttl' => $ttl]);

        $encodedValue = json_encode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $span->setData(['cache.json_error' => json_last_error_msg()]);
            throw new \InvalidArgumentException('Unable to encode value as JSON');
        }

        if ($ttl > 0) {
            $this->client->setex($key, $ttl, $encodedValue);
        } else {
            $this->client->set($key, $encodedValue);
        }

        $span->finish();
    }

    public function remove(string $key): void
    {
        $span = $this->startSpan('cache.remove', ['key' => $key]);
        $this->client->del($key);
        $span->finish();
    }

    public function exists(string $key): bool
    {
        $span = $this->startSpan('cache.exists', ['key' => $key]);
        $exists = $this->client->exists($key) > 0;
        $span->finish();

        return $exists;
    }

    protected function startSpan(string $operation, array $data = []): \Sentry\Tracing\Span
    {
        $hub = SentrySdk::getCurrentHub();
        $span = $hub->getSpan();

        if ($span === null) {
            // No active span, start a new transaction
            $transactionContext = new TransactionContext();
            $transactionContext->setName('cronjob');
            $transactionContext->setOp('cron');
            $transaction = $hub->startTransaction($transactionContext);
            $hub->setSpan($transaction);

            $span = $transaction->startChild(new SpanContext());
        } else {
            $span = $span->startChild(new SpanContext());
        }

        $span->setOp($operation);
        $span->setData($data);

        return $span;
    }
}
