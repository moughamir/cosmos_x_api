<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Docs", description="API documentation endpoints")
 */
class DocsController
{
    private string $docsPath;

    public function __construct(string $docsPath)
    {
        $this->docsPath = $docsPath;
    }

    /**
     * @OA\Get(
     *   path="/docs/json",
     *   summary="OpenAPI JSON",
     *   tags={"Docs"},
     *   @OA\Response(response=200, description="OpenAPI document",
     *     @OA\MediaType(mediaType="application/json")
     *   )
     * )
     */
    public function getOpenApiJson(Request $request, Response $response): Response
    {
        try {
            // Generate OpenAPI documentation by scanning the codebase
            $openapi = \OpenApi\Generator::scan([__DIR__ . '/../']);
            
            // Convert to JSON and output
            $response->getBody()->write($openapi->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            // Log the error
            error_log('Error generating OpenAPI documentation: ' . $e->getMessage());
            
            // Return a 500 error with a JSON response
            $response->getBody()->write(json_encode([
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to generate API documentation',
                    'details' => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null
                ]
            ], JSON_PRETTY_PRINT));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * @OA\Get(
     *   path="/docs",
     *   summary="Swagger UI",
     *   tags={"Docs"},
     *   @OA\Response(response=200, description="Swagger UI HTML")
     * )
     */
    public function getSwaggerUi(Request $request, Response $response): Response
    {
        $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cosmos API Documentation</title>
            <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3/swagger-ui.css">
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
            <script>
                window.onload = function() {
                    const ui = SwaggerUIBundle({
                        url: '/cosmos/docs/json',
                        dom_id: '#swagger-ui',
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIBundle.SwaggerUIStandalonePreset
                        ],
                        layout: "BaseLayout",
                        deepLinking: true,
                        showExtensions: true,
                        showCommonExtensions: true,
                        docExpansion: 'list',
                        tagsSorter: 'alpha',
                        operationsSorter: 'alpha'
                    });
                };
            </script>
        </body>
        </html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
