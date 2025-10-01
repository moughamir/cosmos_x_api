<?php

namespace App;

use DI\ContainerBuilder;
use PDO;
use Psr\Log\LoggerInterface;
use Redis;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

use App\Controllers\ApiController;
use App\Controllers\DocsController;
use App\Controllers\HealthController;
use App\Factory\LoggerFactory;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\ErrorHandlerMiddleware;
use App\Renderer\JsonErrorRenderer;
use App\Services\HealthCheckService;
use App\Services\ImageProxy;
use App\Services\ImageService;
use App\Services\ProductService;

class App
{
    public static function bootstrap(): \Slim\App
    {
        $config = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            PDO::class => function () use ($dbConfig) {
                $dbFile = $dbConfig['db_file'];
                $pdo = new PDO("sqlite:" . $dbFile);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;
            },
            Redis::class => function () use ($config) {
                $redis = new Redis();
                $redis->connect($config['redis']['host'], $config['redis']['port']);
                if (!empty($config['redis']['password'])) {
                    $redis->auth($config['redis']['password']);
                }
                return $redis;
            },
            ProductService::class => function ($container) {
                return new ProductService($container->get(PDO::class));
            },
            ImageService::class => function ($container) {
                return new ImageService($container->get(PDO::class));
            },
            \App\Services\SimilarityService::class => function ($container) {
                return new \App\Services\SimilarityService($container->get(PDO::class));
            },
            ApiController::class => function ($container) {
                return new ApiController(
                    $container->get(ProductService::class),
                    $container->get(ImageService::class),
                    $container->get(\App\Services\SimilarityService::class)
                );
            },
            ImageProxy::class => function () use ($config) {
                return new ImageProxy($config);
            },
            HealthCheckService::class => function ($container) use ($config) {
                return new HealthCheckService(
                    $container->get(PDO::class),
                    $container->get(Redis::class),
                    $container->get(LoggerInterface::class),
                    $config
                );
            },
            HealthController::class => function ($container) {
                return new HealthController($container->get(HealthCheckService::class));
            },
            DocsController::class => function () {
                return new DocsController(__DIR__ . '/../config');
            },
        ]);

        $container = $containerBuilder->build();

        $logger = LoggerFactory::create('app', __DIR__ . '/../logs/app.log');
        $container->set(LoggerInterface::class, $logger);

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Determine and set base path: default to '/cosmos' unless running the PHP built-in server
        $basePath = getenv('BASE_PATH');
        if ($basePath === false || $basePath === '') {
            $basePath = PHP_SAPI === 'cli-server' ? '' : '/cosmos';
        }
        if (!empty($basePath)) {
            $app->setBasePath($basePath);
        }

        // Ensure required directories exist for logs, cache, uploads, and sqlite data
        foreach ([__DIR__ . '/../logs', __DIR__ . '/../var/cache', __DIR__ . '/../public/uploads', __DIR__ . '/../config/data/sqlite'] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        $app->addRoutingMiddleware();
        $app->add(new CorsMiddleware($config));

        $errorMiddleware = $app->addErrorMiddleware(getenv('APP_ENV') === 'development', true, true, $logger);
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        $errorHandler->registerErrorRenderer('application/json', JsonErrorRenderer::class);

        $app->add(new ErrorHandlerMiddleware($logger, getenv('APP_ENV') === 'development'));

        $app->group('/products', function (RouteCollectorProxy $group) {
            $group->get('[/]', [ApiController::class, 'getProducts']);
            $group->get('/search', [ApiController::class, 'searchProducts']);
            $group->get('/{key}', [ApiController::class, 'getProductOrHandle']);
            $group->get('/{key}/related', [ApiController::class, 'getRelated']);
        })->add(new ApiKeyMiddleware($config['api_key']));

        $app->group('/collections', function (RouteCollectorProxy $group) {
            $group->get('/{handle}', [ApiController::class, 'getCollectionProducts']);
        })->add(new ApiKeyMiddleware($config['api_key']));

        $app->get('/image-proxy', [ImageProxy::class, 'output']);

        $app->get('/health', [HealthController::class, 'healthCheck']);
        $app->get('/ping', [HealthController::class, 'ping']);
        $app->get('/version', [HealthController::class, 'version']);

        $app->group('/docs', function (RouteCollectorProxy $group) {
            $group->get('', [DocsController::class, 'getSwaggerUi']);
            $group->get('/json', [DocsController::class, 'getOpenApiJson']);
        });

        return $app;
    }
}