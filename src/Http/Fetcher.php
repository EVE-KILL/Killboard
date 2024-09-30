<?php

namespace EK\Http;

use EK\Cache\Cache;
use EK\Logger\Logger;
use EK\Models\Proxies;
use EK\RateLimiter\RateLimiter;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\RateLimiter\LimiterInterface;

class Fetcher
{
    protected string $baseUri = "";
    protected string $userAgent = "EK/1.0";
    protected string $bucketName = "global";
    protected bool $useProxy = false;
    protected int $rateLimit = 1000;
    protected int $timeout = 30;
    protected LimiterInterface $limiter;

    public function __construct(
        protected Cache $cache,
        protected Proxies $proxies,
        protected RateLimiter $rateLimiter,
        protected Logger $logger
    ) {
        $this->limiter = $rateLimiter->createRateLimiter(
            $this->bucketName,
            'sliding_window',
            $this->rateLimit
        );
    }

    public function fetch(
        string $path,
        string $requestMethod = "GET",
        array $query = [],
        string $body = "",
        array $headers = [],
        array $options = [],
        ?string $proxy_id = null,
        ?int $cacheTime = null,
        bool $ignorePause = false
    ): array {
        // Start a Sentry span for the fetch operation
        $span = $this->startSpan('http.client', 'HTTP Client Request', [
            'path' => $path,
            'requestMethod' => $requestMethod,
            'query' => $query,
            'headers' => $headers,
            'options' => $options,
            'cacheTime' => $cacheTime,
            'ignorePause' => $ignorePause,
        ]);

        // Sort the query, headers, and options
        ksort($query);
        ksort($headers);
        ksort($options);

        // Generate a cache key
        $cacheKey = $this->cache->generateKey($path, $query, $headers);

        // Check if the data is in the cache
        if ($cacheTime > 1 && $this->cache->exists($cacheKey)) {
            $result = $this->getResultFromCache($cacheKey);
            if ($result !== null) {
                return $result;
            }
        }

        // If Proxy usage is enabled, and proxy_id isn't null, we need to fetch a proxy to use
        $proxy = null;
        if ($proxy_id !== null) {
            $proxy = $this->proxies->findOne(["proxy_id" => $proxy_id], cacheTime: 0);
        } elseif ($this->useProxy === true) {
            $proxy = $this->proxies->getRandomProxy();
            if (empty($proxy)) {
                throw new \Exception("No proxies available");
            }
        }

        // If the fetcher is paused, sleep for the paused time
        if ($ignorePause === false) {
            retrySleep:
            $paused = $this->cache->get('fetcher_paused') ?? 0;
            if ($paused > 0) {
                sleep($paused);
                goto retrySleep;
            }
        }

        // Use the rate limiter to prevent spamming the endpoint
        $limit = $this->limiter->reserve(1);
        $limit->wait();

        // Start time for the request
        $startTime = microtime(true);

        // Get the client
        $client = $this->getClient($proxy);

        // Execute the request
        $response = $client->request($requestMethod, $path, [
            "query" => $query,
            "body" => $body,
            "headers" => array_merge($headers, [
                "User-Agent" => $this->userAgent,
            ]),
            "options" => $options,
            "timeout" => $this->timeout,
            "http_errors" => false,
        ]);

        $span
            ->setDescription("$requestMethod $path")
            ->setStatus(\Sentry\Tracing\SpanStatus::createFromHttpStatusCode($response->getStatusCode()))
            ->setData([
                'http.request.method' => $requestMethod,
                'http.response.body.size' => $response->getBody()->getSize(),
                'http.response.status_code' => $response->getStatusCode(),
            ]);

        // Finish time for the request
        $endTime = microtime(true);

        // Get the status code
        $statusCode = $response->getStatusCode();

        // Get the expires header from the response (The Expires and Date are in GMT)
        $now = new \DateTime("now", new \DateTimeZone("GMT"));
        $expires =
            $response->getHeader("Expires")[0] ??
            $now->format("D, d M Y H:i:s T");
        $serverTime =
            $response->getHeader("Date")[0] ?? $now->format("D, d M Y H:i:s T");
        $expiresInSeconds = strtotime($expires) - strtotime($serverTime) ?? 60;

        // Log the request
        $this->logger->debug(
            sprintf(
                "%s %s %s %s",
                $requestMethod,
                $path,
                $statusCode,
                round($response->getBody()->getSize() / 1024, 2) . "KB"
            ),
            [
                "proxy_id" => $proxy["proxy_id"] ?? null,
                "status" => $statusCode,
                "response_time" => $endTime - $startTime,
            ]
        );

        $response = $this->handle($response);

        // Get the content
        $response->getBody()->rewind();
        $content = $response->getBody()->getContents();

        // Store the result in the cache
        if ($cacheTime > 1 && $expiresInSeconds > 0 && in_array($statusCode, [200, 304])) {
            $theCacheTime = $cacheTime ?? $expiresInSeconds;
            $this->cache->set(
                $cacheKey,
                [
                    "headers" => $response->getHeaders(),
                    "body" => $content,
                ],
                $theCacheTime > 0 ? $theCacheTime : 60
            );
        }

        // Finish the span
        $span->finish();

        // Return the result
        return [
            "status" => $statusCode,
            "headers" => $response->getHeaders(),
            "body" => $content,
        ];
    }

    public function handle(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    protected function getClient(?array $proxy = []): Client
    {
        $span = $this->startSpan('http.getClient', 'Get the HTTP client');

        if ($this->useProxy === true) {
            if (!isset($proxy["url"])) {
                throw new \Exception("Proxy URL not set");
            }
        }

        $client = new Client([
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::HEADERS => [
                "User-Agent" => $this->userAgent,
            ],
            'base_uri' => $this->useProxy ? $proxy["url"] : $this->baseUri
        ]);

        $span->finish();
        return $client;
    }

    protected function getResultFromCache(string $cacheKey): ?array
    {
        $span = $this->startSpan('http.getResultFromCache', 'Get result from cache', ['cacheKey' => $cacheKey]);

        $result = $this->cache->get($cacheKey);
        if ($result === null || $result === false) {
            $span->setData(['cache.hit' => false]);
            $span->finish();
            return null;
        }

        $expireTTL = $this->cache->getTTL($cacheKey) ?? 0;
        $expirationTime =
            time() + $expireTTL > 0 ? time() + $expireTTL : time();
        $expireTimeGMT = new \DateTime("now", new \DateTimeZone("GMT"));
        $expireTimeGMT->setTimestamp($expirationTime);
        $currentTimeGMT = new \DateTime("now", new \DateTimeZone("GMT"));

        $span->setData(['cache.hit' => true]);
        $span->finish();

        return [
            "status" => 304,
            "headers" => array_merge($result["headers"], [
                "Expires" => $expireTimeGMT->format("D, d M Y H:i:s \G\M\T"),
                "Last-Modified" => $result["headers"]["Last-Modified"],
                "Cache-Control" =>
                    "public, max-age=" . $this->cache->getTTL($cacheKey),
                "Date" => $currentTimeGMT->format("D, d M Y H:i:s \G\M\T"),
            ]),
            "body" => $result["body"],
        ];
    }

    protected function startSpan(string $operation, string $description, array $data = []): \Sentry\Tracing\Span
    {
        $hub = SentrySdk::getCurrentHub();
        $span = $hub->getSpan();
        if ($span === null) {
            // No active span, start a new transaction
            $transactionContext = new TransactionContext();
            $transactionContext->setName('db');
            $transactionContext->setOp('db');
            $transactionContext->setDescription($description);
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
