<?php

namespace EK\Api\Abstracts;

use Illuminate\Support\Collection;
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
    protected Collection $arguments;

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
            $controller->arguments = new Collection($args);
            $controller->setRequest($request);
            $controller->setResponse($response);
            $controller->setBody($request->getBody()->getContents());

            return call_user_func_array([$controller, $actionName], $args);
        };
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
        if ($this->getArgs()->has($key)) {
            return $this->getArgs()->get($key);
        }
        return null;
    }

    /**
     * Get route arguments
     *
     * @return Collection
     */
    protected function getArgs(): Collection
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
        return $this->getParams()->get($key, $default);
    }

    /**
     * Return all POST/GET Params
     *
     * @return Collection
     */
    protected function getParams(): Collection
    {
        return new Collection($this->request->getQueryParams());
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
        return $this->getPostParams()->get($key);
    }

    /**
     * Return all POST Params
     *
     * @return Collection
     */
    protected function getPostParams(): Collection
    {
        $post = array_diff_key($this->request->getParsedBody(), array_flip([
            '_METHOD',
        ]));

        return new Collection($post);
    }

    protected function getPostData(): string
    {
        return $this->getBody();
    }

    /**
     * Get the files posted
     *
     * @return Collection
     */
    protected function getFiles(): Collection
    {
        $files = array_diff_key($this->request->getUploadedFiles(), array_flip([
            '_METHOD',
        ]));

        return new Collection($files);
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
        return $this->getHeaders()->get($key);
    }

    /**
     * Get all request headers
     *
     * @return Collection
     */
    protected function getHeaders(): Collection
    {
        return new Collection($this->request->getHeaders());
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
     * @param array|Collection $data
     * @param int $status
     * @param String $contentType
     * @param int $cacheTime
     *
     * @return ResponseInterface
     */
    protected function json(
        array|Collection $data = [],
        int $cacheTime = 30,
        int $status = 200,
        string $contentType = 'application/json; charset=UTF-8'
    ): ResponseInterface {
        $response = $this->generateResponse($status, $contentType, $cacheTime);
        $response->getBody()->write(json_encode($data, JSON_NUMERIC_CHECK));

        return $response;
    }

    /**
     * Renders a twig template
     *
     * @param string $template
     * @param array|Collection $data
     * @param int $cacheTime
     * @param int $status
     * @param string $contentType
     *
     * @return ResponseInterface
     */
    protected function render(
        string           $template,
        array|Collection $data = [],
        int              $cacheTime = 0,
        int              $status = 200,
        string           $contentType = 'text/html'
    ): ResponseInterface {
        $render = $this->twig->render($template, $data);
        $response = $this->generateResponse($status, $contentType, $cacheTime);
        $response->getBody()->write($render);

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
            ->withAddedHeader('Access-Control-Allow-Origin', '*')
            ->withAddedHeader('Access-Control-Allow-Methods', '*')
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

    protected function cleanupTimestamps(array $data): array
    {
        foreach ($data as $key => $value) {
            // Check if the value is an instance of UTCDateTime
            if ($value instanceof UTCDateTime) {
                $data[$key] = $value->toDateTime()->format('Y-m-d H:i:s');
            }

            // Check if the value is an array
            if (is_array($value)) {
                // If the array has the structure containing $date and $numberLong
                if (isset($value['$date']['$numberLong'])) {
                    $data[$key] = (new UTCDateTime($value['$date']['$numberLong']))->toDateTime()->format('Y-m-d H:i:s');
                } else {
                    // Recursively process nested arrays
                    $data[$key] = $this->cleanupTimestamps($value);
                }
            }
        }

        return $data;
    }
}
