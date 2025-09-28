<?php

namespace App;

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Middleware\ApiKeyMiddleware;

use App\Services\ImageProxy;
use App\Services\ProductService;
use App\Services\ImageService;
use PDO;
use Slim\Routing\RouteCollectorProxy;

class App
{
    public static function bootstrap(): \Slim\App
    {
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        $app = AppFactory::create();
        $app->setBasePath('/cosmos');

        // Middleware
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        // Dependency Injection
        $container = $app->getContainer();

        $container[PDO::class] = function () use ($dbConfig) {
            $dbFile = $dbConfig['db_file'];
            $pdo = new PDO("sqlite:" . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        };

        $container[ProductService::class] = function ($container) {
            return new ProductService($container->get(PDO::class));
        };
        $container[ImageService::class] = function ($container) {
            return new ImageService($container->get(PDO::class));
        };
        $container[ApiController::class] = function ($container) {
            return new ApiController($container->get(ProductService::class), $container->get(ImageService::class));
        };
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
