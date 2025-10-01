<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\\Generator;
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
        $outFile = __DIR__ . '/../../public/openapi.json';
        if (is_file($outFile)) {
            $response->getBody()->write((string)file_get_contents($outFile));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Fallback: generate on the fly with suppressed warnings
        $prevDisplay = ini_get('display_errors');
        $prevReporting = error_reporting();
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_WARNING & ~E_USER_WARNING & ~E_DEPRECATED);
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (strpos($errfile, 'zircote/swagger-php') !== false) { return true; }
            return false;
        });
        try {
            $openapi = Generator::scan([dirname(__DIR__)]);
            if (!is_dir(dirname($outFile))) { @mkdir(dirname($outFile), 0775, true); }
            file_put_contents($outFile, $openapi->toJson(JSON_PRETTY_PRINT));
            $response->getBody()->write((string)file_get_contents($outFile));
            return $response->withHeader('Content-Type', 'application/json');
        } finally {
            restore_error_handler();
            ini_set('display_errors', $prevDisplay);
            error_reporting($prevReporting);
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
