<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class CorsMiddleware implements MiddlewareInterface
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        $allowedOrigins = $this->config['cors']['allowed_origins'] ?? [];
        $origin = $request->getHeaderLine('Origin');

        if (in_array('*', $allowedOrigins)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $allowedOrigins)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');

        return $response;
    }
}
