<?php
// index.php
require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Controllers\ApiController;
use App\Services\ImageProxy;
use App\Middleware\ApiKeyMiddleware;
use Slim\Routing\RouteCollectorProxy;

$config = require __DIR__ . '/config/app.php';

$app = AppFactory::create();
// IMPORTANT: Set the base path to match your deployment directory
$app->setBasePath('/cosmos');
$app->addRoutingMiddleware();
// Set to true, true, true for development (shows errors)
$app->addErrorMiddleware(true, true, true); 

$api = new ApiController($config['db_file']);
$imageProxy = new ImageProxy($config);

// --- 1. PRODUCTS GROUP (Secured with API Key) ---
$app->group('/products', function (RouteCollectorProxy $group) use ($api) {
    // List all products (paginated, with trailing slash fix)
    $group->get('[/]', [$api, 'getProducts']);
    
    // Search products (FTS)
    $group->get('/search', [$api, 'searchProducts']);
    
    // Combined lookup route: /products/{id} OR /products/{handle}
    $group->get('/{key}', [$api, 'getProductOrHandle']); 
    
})->add(new ApiKeyMiddleware($config['api_key']));


// --- 2. COLLECTIONS GROUP (Secured with API Key) ---
$app->group('/collections', function (RouteCollectorProxy $group) use ($api) {
    // Unified collection endpoint: /collections/{handle}
    $group->get('/{handle}', [$api, 'getCollectionProducts']);

})->add(new ApiKeyMiddleware($config['api_key']));


// --- 3. IMAGE PROXY (Requires separate logic in src/Services/ImageProxy.php) ---
$app->get('/image-proxy', [$imageProxy, 'output']);

$app->run();
