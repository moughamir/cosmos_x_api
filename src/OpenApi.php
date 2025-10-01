<?php

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *   @OA\Info(
 *     version="1.0.0",
 *     title="Cosmos Products API",
 *     description="Read-only products API with MessagePack support",
 *     @OA\Contact(email="support@example.com"),
 *     @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 *   ),
 *   @OA\Server(url="/cosmos", description="API Server (context path)"),
 *   @OA\SecurityScheme(securityScheme="api_key", type="apiKey", in="header", name="X-API-KEY")
 * )
 */
