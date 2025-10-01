<?php

namespace App\Controllers;

use OpenApi\Annotations as OA;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\HealthCheckService;

/**
 * @OA\Tag(
 *     name="Health",
 *     description="Health check endpoints"
 * )
 */
class HealthController
{
    private HealthCheckService $healthCheck;

    public function __construct(HealthCheckService $healthCheck)
    {
        $this->healthCheck = $healthCheck;
    }

    /**
     * @OA\Get(
     *     path="/health",
     *     summary="Get health status of the API and its dependencies",
     *     tags={"Health"},
     *     @OA\Response(
     *         response=200,
     *         description="API is healthy",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="healthy")
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="API is unhealthy",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="unhealthy")
     *         )
     *     )
     * )
     */
    public function healthCheck(Request $request, Response $response): Response
    {
        $status = $this->healthCheck->check();

        $response->getBody()->write(json_encode($status, JSON_PRETTY_PRINT));
        return $response
            ->withStatus($status['status'] === 'healthy' ? 200 : 503)
            ->withHeader('Content-Type', 'application/health+json');
    }

    /**
     * @OA\Get(
     *     path="/ping",
     *     summary="Simple ping endpoint",
     *     tags={"Health"},
     *     @OA\Response(
     *         response=200,
     *         description="Returns 'pong' if the API is running",
     *         @OA\JsonContent(
     *             type="string",
     *             example="pong"
     *         )
     *     )
     * )
     */
    public function ping(Request $request, Response $response): Response
    {
        $response->getBody()->write('pong');
        return $response->withHeader('Content-Type', 'text/plain');
    }

    /**
     * @OA\Get(
     *     path="/version",
     *     summary="Get API version information",
     *     tags={"Health"},
     *     @OA\Response(
     *         response=200,
     *         description="Returns version information",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", example="Cosmos Products API"),
     *             @OA\Property(property="version", type="string", example="1.0.0"),
     *             @OA\Property(property="environment", type="string", example="production")
     *         )
     *     )
     * )
     */
    public function version(Request $request, Response $response): Response
    {
        $version = [
            'name' => 'Cosmos Products API',
            'version' => getenv('APP_VERSION') ?: 'dev',
            'environment' => getenv('APP_ENV') ?: 'development',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];

        $response->getBody()->write(json_encode($version, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
