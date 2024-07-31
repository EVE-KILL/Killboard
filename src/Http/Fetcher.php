<?php

namespace EK\Http;

use bandwidthThrottle\tokenBucket\BlockingConsumer;
use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\storage\FileStorage;
use bandwidthThrottle\tokenBucket\TokenBucket;
use EK\Cache\Cache;
use EK\Logger\FileLogger;
use EK\Models\Proxies;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class Fetcher
{
    protected string $baseUri = "";
    protected string $userAgent = "EK/1.0";
    protected string $bucketName = "global";
    protected bool $useProxy = false;
    protected bool $useThrottle = false;
    protected int $bucketLimit = 100;
    protected int $timeout = 30;
    protected BlockingConsumer $throttleBucket;
    protected FileLogger $logger;

    public function __construct(
        protected Cache $cache,
        protected Proxies $proxies
    ) {
        $this->throttleBucket = $this->generateBucket();
        $this->logger = new FileLogger(
            BASE_DIR . "/logs/" . $this->bucketName . ".log",
            $this->bucketName . "-logger"
        );
    }

    private function generateBucket(): BlockingConsumer
    {
        $bucketPath = "/tmp/{$this->bucketName}_rate_limit.bucket";
        $storage = new FileStorage($bucketPath);
        $rate = new Rate($this->bucketLimit, Rate::SECOND);
        $bucket = new TokenBucket($this->bucketLimit, $rate, $storage);
        $bucket->bootstrap($this->bucketLimit);
        return new BlockingConsumer($bucket);
    }

    public function fetch(
        string $path,
        string $requestMethod = "GET",
        array $query = [],
        string $body = "",
        array $headers = [],
        array $options = [],
        ?string $proxy_id = null,
        ?int $cacheTime = null
    ): array {
        // Sort the query, headers and options
        ksort($query);
        ksort($headers);
        ksort($options);

        // Generate a cache key
        $cacheKey = $this->cache->generateKey($path, $query, $headers);

        // Check if the data is in the cache
        if ($this->cache->exists($cacheKey)) {
            $result = $this->getResultFromCache($cacheKey);
            if ($result !== null) {
                return $result;
            }
        }

        // If Proxy usage is enabled, and proxy_id isn't null, we need to fetch a proxy to use
        $proxy = null;
        if ($proxy_id !== null && $this->useProxy === true) {
            $proxy = $this->proxies
                ->findOne(["proxy_id" => $proxy_id])
                ->toArray();
        } elseif ($this->useProxy === true) {
            $proxy = $this->proxies->getRandomProxy();
            if (empty($proxy)) {
                throw new \Exception("No proxies available");
            }
        }

        // If we have a throttle bucket, we consume a token to rate limit ourselves
        if ($this->useThrottle === true) {
            // Ignore warnings, because the bucket converts float to int
            // and we don't care about that, convert away!
            @$this->throttleBucket->consume(1);
        }

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
        $this->logger->info(
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
        if ($expiresInSeconds > 0 && in_array($statusCode, [200, 304])) {
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
        if ($this->useProxy === true) {
            if (!isset($proxy["url"])) {
                throw new \Exception("Proxy URL not set");
            }

            return new Client([
                "base_uri" => $proxy["url"],
            ]);
        }

        return new Client([
            "base_uri" => $this->baseUri,
        ]);
    }

    protected function getResultFromCache(string $cacheKey): ?array
    {
        $result = $this->cache->get($cacheKey);
        if ($result === null || $result === false) {
            return null;
        }

        $expireTTL = $this->cache->getTTL($cacheKey) ?? 0;
        $expirationTime =
            time() + $expireTTL > 0 ? time() + $expireTTL : time();
        $expireTimeGMT = new \DateTime("now", new \DateTimeZone("GMT"));
        $expireTimeGMT->setTimestamp($expirationTime);
        $currentTimeGMT = new \DateTime("now", new \DateTimeZone("GMT"));

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
}
