<?php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Middleware\ApiKeyMiddleware;

use App\Services\ImageProxy;
use Slim\Routing\RouteCollectorProxy;

class App
{
    public static function bootstrap(): \Slim\App
    {
        $config = require __DIR__ . '/../../config/app.php';

        $app = AppFactory::create();
        $app->setBasePath('/cosmos');

        // Middleware
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        // Dependency Injection
        $container = $app->getContainer();
        $container[ImageProxy::class] = function () use ($config) {
            return new ImageProxy($config);
        };

        // Routes
        $app->group('/products', function (RouteCollectorProxy $group) {
            $group->get('[/]', [ApiController::class, 'getProducts']);
            $group->get('/search', [ApiController::class, 'searchProducts']);
            $group->get('/{key}', [ApiController::class, 'getProductOrHandle']);
        })->add(new ApiKeyMiddleware($config['api_key']));

        $app->group('/collections', function (RouteCollectorProxy $group) {
            $group->get('/{handle}', [ApiController::class, 'getCollectionProducts']);
        })->add(new ApiKeyMiddleware($config['api_key']));

        $app->get('/image-proxy', [ImageProxy::class, 'output']);

        return $app;
    }
}
