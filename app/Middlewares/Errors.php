<?php

namespace EK\Middlewares;

use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\Psr17\Psr17Factory;
use Slim\Psr7\Factory\ResponseFactory;

class Errors implements MiddlewareInterface
{
    public function __construct(
        protected ResponseFactory $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException $e) {
            $response = $this->responseFactory->createResponse(404);
            $response->getBody()->write($this->emitErrorText($request, 404, 'Not Found'));
            return $response;
        } catch (HttpMethodNotAllowedException $e) {
            $response = $this->responseFactory->createResponse(405);
            $response->getBody()->write($this->emitErrorText($request, 405, 'Method Not Allowed'));
            return $response;
        }
    }

    private function emitErrorText(ServerRequestInterface $request, string $errorType = '404', string $errorMessage = 'Not found'): string
    {
        $acceptHeaders = explode(',', $request->getHeader('accept')[0] ?? '');
        foreach ($acceptHeaders as $acceptHeader) {
            $render = match ($acceptHeader) {
                'application/json' => function () use ($errorType, $errorMessage) {
                    return json_encode(['error' => $errorType, 'message' => $errorMessage]);
                },
                'application/xml', 'text/xml' => function () use ($errorType, $errorMessage) {
                    return '<?xml version="1.0" encoding="UTF-8"?><error>' . $errorType . '</error><message>' . $errorMessage . '</message>';
                },
                default => function () use ($errorType, $errorMessage) {
                    return $errorType . ': ' . $errorMessage;
                }
            };
        }

        return $render();
    }
}
