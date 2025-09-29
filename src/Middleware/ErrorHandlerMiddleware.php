<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response as SlimResponse;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private bool $displayErrorDetails;

    public function __construct(LoggerInterface $logger, bool $displayErrorDetails = false)
    {
        $this->logger = $logger;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = new SlimResponse();
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            
            $error = [
                'error' => [
                    'message' => $this->displayErrorDetails ? $e->getMessage() : 'An error occurred',
                    'code' => $statusCode,
                ]
            ];

            if ($this->displayErrorDetails) {
                $error['error']['details'] = [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ];
            }

            $response->getBody()->write(json_encode($error));
            return $response
                ->withStatus($statusCode)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
