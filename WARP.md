# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

Project overview
- Stack: PHP 8.3, Slim 4 (PSR-7), PHP-DI, SQLite (PDO), Redis (optional), Monolog, swagger-php.
- Purpose: Read-only API exposing product data with JSON and optional MessagePack responses. Designed to run behind Apache (public/ as DocumentRoot) and also via Docker.
- Base path: Routes are mounted under /cosmos (e.g., /cosmos/health, /cosmos/products). Apache config and tests assume this context path.

Common commands
- Install dependencies
```bash path=null start=null
composer install
```

- Run full test suite
```bash path=null start=null
composer test
```

- Run a single test file
```bash path=null start=null
./vendor/bin/phpunit tests/AppTest.php
```

- Run a single test by name
```bash path=null start=null
./vendor/bin/phpunit --filter 'AppTest::test_health_check_returns_healthy_status'
```

- Lint (PHP_CodeSniffer)
```bash path=null start=null
./vendor/bin/phpcs --standard=PSR12 src tests
```

- Auto-fix lint issues (where possible)
```bash path=null start=null
./vendor/bin/phpcbf --standard=PSR12 src tests
```

- Generate OpenAPI JSON to public/openapi.json
```bash path=null start=null
composer docs
```

- Serve locally with PHP’s built-in server (no Apache context path)
  Note: endpoints will be under / (e.g., http://localhost:8080/health) instead of /cosmos/*.
```bash path=null start=null
php -S 127.0.0.1:8080 -t public/
```

Docker and services
- Start prod-like stack (Apache + app on :8080, Redis)
```bash path=null start=null
docker compose up -d app redis
```

- Start dev stack with live code mount (app on :8081)
```bash path=null start=null
docker compose up -d dev redis
```

- Tail logs
```bash path=null start=null
docker compose logs -f app
```

- Health check (requires API key when hitting protected routes; health itself is public)
```bash path=null start=null
curl http://localhost:8080/cosmos/health
```

Configuration and environment
- SQLite path: defaults to config/data/sqlite/products.sqlite. Override with DB_FILE.
- Base path: BASE_PATH controls Slim’s base path. Default in containers is /cosmos; when using PHP’s built-in server, base path is disabled for convenience.
- API key: set via API_KEY env. Protected groups (/products, /collections) require X-API-KEY header.
- C- Example (bash) for local non-Docker runs:
```bash path=null start=null
export DB_FILE="$(pwd)/config/data/sqlite/products.sqlite"
export API_KEY={{API_KEY}}
export CORS_ALLOWED_ORIGINS=*
export REDIS_HOST=127.0.0.1
export REDIS_PORT=6379
# BASE_PATH is ignored when using the PHP built-in server (we auto-disable it), but can be set for completeness
export BASE_PATH=/cosmos
php -S 127.0.0.1:8080 -t public/
```

API surface (high-level)
- Health and meta (public)
  - GET /cosmos/health → overall status (includes db, redis, storage, memory)
  - GET /cosmos/ping → pong
  - GET /cosmos/version → name, version, env, timestamp
- Products (require X-API-KEY)
  - GET /cosmos/products?page&limit&format → paginated products with images and meta
  - GET /cosmos/products/search?q&fields&format → FTS across products via products_fts
  - GET /cosmos/products/{idOrHandle}?format → by numeric ID or handle
- Collections (require X-API-KEY)
  - GET /cosmos/collections/{handle}?page&limit&fields&format → handles: all, featured, sale, new, bestsellers, trending
- Docs
  - GET /cosmos/docs → Swagger UI
  - GET /cosmos/docs/json → OpenAPI JSON (generated at runtime via annotations)

MessagePack responses
- For endpoints that return data, add query param format=msgpack to request application/x-msgpack when the msgpack PHP extension is available. Otherwise JSON is returned.

Architecture (big picture)
- Entry points
  - public/index.php boots the Slim app and runs it. Apache is configured to serve public/ and rewrite to index.php.
  - apache-config.conf sets DocumentRoot to public/ and enables rewrites.
- Bootstrap and DI
  - src/App.php builds a PHP-DI container and wires services: PDO (SQLite from config/database.php), Redis, ProductService, ImageService, HealthCheckService, logger, controllers, DocsController.
  - Monolog is provided via src/Factory/LoggerFactory.php. Error handling is standardized by ErrorHandlerMiddleware with JSON responses and detailed traces in development.
- HTTP layer
  - Slim 4 app with routing and middleware stack:
    - Routing middleware
    - CORS middleware (src/Middleware/CorsMiddleware.php), configured via CORS_ALLOWED_ORIGINS
    - Error middleware with JsonErrorRenderer for application/json
    - Custom ErrorHandlerMiddleware for unified error payloads
    - ApiKeyMiddleware on /products and /collections enforcing X-API-KEY === API_KEY
- Controllers and endpoints
  - Controllers under src/Controllers/* provide handlers for health, products, collections, and docs. Swagger annotations in controllers and models are scanned at runtime for /docs/json.
- Services and data access
  - SQLite via PDO with a simple schema: products, product_images, and a products_fts virtual table for FTS. ProductService encapsulates queries, including FTS and collection selectors. ImageService fetches images by product or by list of product IDs.
- Models
  - Plain PHP classes for Product and Image reflect table shapes. Product::ALLOWED_PRODUCT_FIELDS constrains field selection for search/collections.
  - MsgPackResponse wraps PSR-7 responses to emit MessagePack when available.
- Config
  - config/app.php: API key, image proxy config, CORS, rate limit placeholders, Redis.
  - config/database.php: DB_FILE path fallback.

Gotchas and tips specific to this repo
- Context path: The app is expected to live under /cosmos when served by Apache or Docker. If you use the built-in PHP server, hit /health instead of /cosmos/health.
- Database file: Ensure config/data/sqlite/products.sqlite exists (see README). Without it, health check database probe will fail and product endpoints will return no data.
- API key header casing: Middleware checks X-API-KEY (case-insensitive). Provide that header for /products/* and /collections/* routes.

If WARP is extending this project
- Keep new endpoints under /cosmos, add routes in src/App.php, and group them behind ApiKeyMiddleware if they should be protected.
- For new handlers, follow the controller/service split used here and prefer injecting dependencies via the container in src/App.php.
