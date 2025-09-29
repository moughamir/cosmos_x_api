<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Generator;

class DocsController
{
    private string $docsPath;

    public function __construct(string $docsPath)
    {
        $this->docsPath = $docsPath;
    }

    public function getOpenApiJson(Request $request, Response $response): Response
    {
        $openapi = Generator::scan([__DIR__ . '/../']);
        $response->getBody()->write($openapi->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

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
