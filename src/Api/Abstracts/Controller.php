<?php

namespace EK\Api\Abstracts;

use Generator;
use MongoDB\BSON\UTCDateTime;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirius\Validation\Validator;

abstract class Controller
{
    protected ServerRequestInterface $request;
    protected ResponseInterface $response;
    protected string $body;
    private array $preload = [];
    protected array $routes = [];
    protected Validator $validator;
    protected array $arguments;

    public function __construct(
    ) {
        $this->validator = new Validator();
    }

    public function __invoke(string $actionName = 'handle'): \Closure
    {
        $controller = $this;

        return function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ) use (
            $controller,
            $actionName
        ) {
            // Start a transaction with a context for the controller action
            $transactionContext = new \Sentry\Tracing\TransactionContext();
            $transactionContext->setName($actionName);
            $transactionContext->setOp('http.server');

            $transaction = \Sentry\startTransaction($transactionContext);
            \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

            // Start a span for the controller operation
            $spanContext = new \Sentry\Tracing\SpanContext();
            $spanContext->setOp('http.request');
            $spanContext->setData([
                'url' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'args' => $args,
            ]);
            $span = $transaction->startChild($spanContext);
            \Sentry\SentrySdk::getCurrentHub()->setSpan($span);

            try {
                // Setup the controller with request, response, and arguments
                $controller->arguments = $args;
                $controller->setRequest($request);
                $controller->setResponse($response);
                $controller->setBody($request->getBody()->getContents());

                // Call the appropriate controller action
                $result = call_user_func_array([$controller, $actionName], $args);

                return $result;
            } catch (\Throwable $e) {
                // Capture any exception and report it to Sentry
                \Sentry\SentrySdk::getCurrentHub()->captureException($e);
                throw $e; // Re-throw the exception
            } finally {
                // Ensure the span and transaction are finished
                $span->finish();
                \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
                $transaction->finish();

                // Reset the Sentry state to prevent data leakage between requests
                \Sentry\SentrySdk::getCurrentHub()->popScope();
            }
        };
    }

    protected function newValidator(): Validator
    {
        return new Validator();
    }

    protected function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    protected function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }

    protected function setBody(string $body): void
    {
        $this->body = $body;
    }

    protected function getBody(): string
    {
        return $this->body;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get a single route argument
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getArg(string $key): mixed
    {
        if (isset($this->arguments[$key])) {
            return $this->arguments[$key];
        }

        return null;
    }

    /**
     * Get route arguments
     *
     * @return array
     */
    protected function getArgs(): array
    {
        return $this->arguments;
    }

    /**
     * Return a single POST/GET Param
     *
     * @param string $key
     *
     * @return string|bool|null
     */
    protected function getParam(string $key, mixed $default = null): mixed
    {
        $result = $this->getParams()[$key] ?? $default;
        return $result;
    }

    /**
     * Return all POST/GET Params
     *
     * @return array
     */
    protected function getParams(): array
    {
        return $this->request->getQueryParams();
    }

    /**
     * Return a single POST Param
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getPostParam(string $key): mixed
    {
        return $this->getPostParams()[$key];
    }

    /**
     * Return all POST Params
     *
     * @return array
     */
    protected function getPostParams(): array
    {
        $post = array_diff_key($this->request->getParsedBody() ?? [], array_flip([
            '_METHOD',
        ]));

        return $post;
    }

    protected function getPostData(): string
    {
        return $this->getBody();
    }

    /**
     * Get the files posted
     *
     * @return array
     */
    protected function getFiles(): array
    {
        $files = array_diff_key($this->request->getUploadedFiles(), array_flip([
            '_METHOD',
        ]));

        return $files;
    }

    /**
     * Get a single request header
     *
     * @param string $key
     *
     * @return string|null
     */
    protected function getHeader(string $key): ?string
    {
        return $this->getHeaders()[$key];
    }

    /**
     * Get all request headers
     *
     * @return array
     */
    protected function getHeaders(): array
    {
        return $this->request->getHeaders();
    }

    /**
     * Tells the web client to preload a resource, can be image, css, media, etc.
     * Refer to https://www.w3.org/TR/preload/#server-push-(http/2) for more info
     *
     * @param string $urlPath local path or remote http/https
     */
    protected function preload(string $urlPath): void
    {
        $this->preload[] = "<{$urlPath}>; rel=preload;";
    }

    /**
     * @param $dateTime
     *
     * @return UTCDateTime
     */
    protected function makeTimeFromDateTime(string $dateTime): UTCDateTime
    {
        $unixTime = strtotime($dateTime);
        $milliseconds = $unixTime * 1000;

        return new UTCDateTime($milliseconds);
    }

    /**
     * @param $unixTime
     *
     * @return UTCDateTime
     */
    protected function makeTimeFromUnixTime(int $unixTime): UTCDateTime
    {
        $milliseconds = $unixTime * 1000;

        return new UTCDateTime($milliseconds);
    }

    /**
     * Output html data
     *
     * @param string $htmlData
     * @param int $cacheTime
     * @param int $status
     * @param string $contentType
     *
     * @return ResponseInterface
     */
    protected function html(
        string $htmlData,
        int $cacheTime = 0,
        int $status = 200,
        string $contentType = 'text/html'
    ): ResponseInterface {
        $response = $this->generateResponse($status, $contentType, $cacheTime);
        $response->getBody()->write($htmlData);

        return $response;
    }

    /**
     * Render the data as json output
     *
     * @param array $data
     * @param int $status
     * @param String $contentType
     * @param int $cacheTime
     *
     * @return ResponseInterface
     */
    protected function json(
        array $data = [],
        int $cacheTime = 30,
        int $status = 200,
        string $contentType = 'application/json; charset=UTF-8'
    ): ResponseInterface {
        $response = $this->generateResponse($status, $contentType, $cacheTime);
        $response->getBody()->write(json_encode($data));

        return $response;
    }

    /**
     * Generates the response for the output types, render, json, xml and html
     *
     * @param int $status
     * @param string $contentType
     * @param int $cacheTime
     *
     * @return ResponseInterface
     */
    protected function generateResponse(int $status, string $contentType, int $cacheTime): ResponseInterface
    {
        $response = $this->response->withStatus($status)
            ->withHeader('Content-Type', $contentType)
            ->withAddedHeader('X-Server', 'EVE-KILL/1.0');

        if ($cacheTime > 0) {
            $response = $response
                ->withAddedHeader('Expires', gmdate('D, d M Y H:i:s', time() + $cacheTime))
                ->withAddedHeader('Cache-Control', "public, max-age={$cacheTime}, proxy-revalidate");
        }

        if (!empty($this->preload)) {
            foreach ($this->preload as $preload) {
                $response = $response->withAddedHeader('Link', $preload);
            }
        }

        return $response;
    }

    /**
     * Get the full path of the request (http://mydomain.tld/request/requestData)
     * @return string
     */
    protected function getFullPath(): string
    {
        $port = $this->request->getServerParams()['SERVER_PORT'];

        return $this->request->getUri()->getScheme() . '://' .
            $this->request->getUri()->getHost() . ':' .
            $port . '/' .
            $this->request->getUri()->getPath();
    }

    /**
     * Get the full host of the request (http://mydomain.tld/)
     * @return string
     */
    protected function getFullHost(): string
    {
        $port = $this->request->getServerParams()['SERVER_PORT'];

        return "{$this->request->getUri()->getScheme()}://{$this->request->getUri()->getHost()}:{$port}";
    }

    /**
     * Redirect.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param string $url The redirect destination.
     *
     * @return ResponseInterface
     */
    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        $response = $this->generateResponse($status, 'text/html', 0);
        $response = $response->withHeader('Location', $url);
        $response->getBody()->write('');

        return $response;
    }

    protected function cleanupTimestamps(array|Generator $data): array
    {
        $returnData = [];

        foreach ($data as $key => $value) {
            $returnData[$key] = $value;
            // Check if the value is an instance of UTCDateTime
            if ($value instanceof UTCDateTime) {
                $returnData[$key] = $value->toDateTime()->getTimestamp();
            }

            // Check if the value is an array
            if (is_array($value)) {
                // If the array has the structure containing $date and $numberLong
                if (isset($value['$date']['$numberLong'])) {
                    $returnData[$key] = (new UTCDateTime((int)$value['$date']['$numberLong']))->toDateTime()->getTimestamp();
                } else {
                    // Recursively process nested arrays
                    $returnData[$key] = $this->cleanupTimestamps($value);
                }
            }
        }

        return $returnData;
    }
}
