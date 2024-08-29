<?php

namespace EK\Middlewares;

use EK\Config\Config;
use EK\Logger\Logger;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Whoops\Run;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\XmlResponseHandler;
use Whoops\Handler\JsonResponseHandler;

class Whoops implements MiddlewareInterface
{
    public function __construct(
        protected ResponseFactory $responseFactory,
        protected Config $config,
        protected Logger $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException|HttpMethodNotAllowedException $e) {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $developmentMode = $this->config->get('development', false);

            if ($developmentMode === false) {
                // Generate a unique exception ID
                $exceptionId = uniqid('exception_', true);

                // Simplified exception logging
                $this->logSimplifiedException($e, $exceptionId);

                // Prepare a generic error message for the user
                $response = $this->responseFactory->createResponse(500);
                $acceptHeaders = explode(',', $request->getHeader('accept')[0] ?? '');

                $render = match ($acceptHeaders[0]) {
                    'application/json' => json_encode(['error' => $e->getMessage(), 'message' => 'An error occurred. Please contact support with the following ID.', 'exceptionId' => $exceptionId]),
                    'application/xml', 'text/xml' => (new \SimpleXMLElement('<error>' . $e->getMessage() . '</error></message>An error occurred. Please contact support with the following ID.</message><exceptionId>' . $exceptionId . '</exceptionId>'))->asXML(),
                    default => $e->getMessage() . '<br>An error occurred. Please contact support with the following ID: ' . $exceptionId,
                };

                $response->getBody()->write($render);
            } else {
                // Handle the exception with Whoops in development mode
                $response = $this->responseFactory->createResponse(500);

                $acceptHeaders = explode(',', $request->getHeader('accept')[0] ?? '');
                $response->getBody()->write($this->renderWhoops($e, $acceptHeaders));
            }
            return $response;
        }
    }

    private function renderWhoops(\Throwable $e, array $acceptHeaders = ['application/json']): string
    {
        $whoops = new Run();
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);

        /** @var PrettyPageHandler|JsonResponseHandler|XmlResponseHandler|PlainTextHandler $handler */
        $handler = null;

        foreach ($acceptHeaders as $acceptHeader) {
            $handler = match ($acceptHeader) {
                'application/json' => new JsonResponseHandler(),
                'application/xml', 'text/xml' => new XmlResponseHandler(),
                'text/plain', 'text/css', 'text/javascript' => new PlainTextHandler(),
                default => new PrettyPageHandler()
            };
        }

        if ($handler instanceof PrettyPageHandler) {
            $handler->handleUnconditionally(true);
            $handler->setEditor('vscode');
        }

        $whoops->prependHandler($handler);
        return $whoops->handleException($e);
    }

    private function logSimplifiedException(\Throwable $e, string $exceptionId): void
    {
        // Create a simplified log message
        $logMessage = sprintf(
            "Exception ID: %s\nMessage: %s\nFile: %s\nLine: %d\nTrace: %s\n",
            $exceptionId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 5)) // Limit trace to 5 lines
        );

        // Log the simplified message
        $this->logger->critical($logMessage, [
            'exception_id' => $exceptionId,
            'exception' => $e,
        ]);
    }
}
