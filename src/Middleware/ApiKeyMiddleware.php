<?php
// src/Middleware/ApiKeyMiddleware.php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class ApiKeyMiddleware
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Check for API Key in the X-API-KEY header
        $headerApiKey = $request->getHeaderLine('X-API-KEY');

        if (empty($headerApiKey) || $headerApiKey !== $this->apiKey) {
            $response = new SlimResponse(401);
            $response->getBody()->write(json_encode(['error' => 'Unauthorized. Missing or invalid API Key.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}