# Project Summary: Cosmos Products API

This document provides a comprehensive summary of the Cosmos Products API project, compiled from the various Markdown files in the repository.

## 1. Project Overview

The Cosmos Products API is a simple, high-performance, read-only API for accessing product data. It is built with PHP 8.3 and the Slim 4 micro-framework, using SQLite for data storage. This makes it lightweight and easy to set up, with no external database dependencies required.

**Key Features:**

*   **Fast & Lightweight:** Minimal footprint, ideal for quick lookups.
*   **RESTful Endpoints:** Access products, collections, and search functionality.
*   **Flexible Output:** Supports both JSON and MessagePack (MsgPack) formats.
*   **Full-Text Search (FTS):** Enabled for product attributes.
*   **Easy to Deploy:** Can be deployed using Docker or a traditional web server.

**Technology Stack:**

*   **Backend:** PHP 8.3, Slim 4, PHP-DI, SQLite (PDO), Monolog, swagger-php
*   **Caching:** Redis (optional)
*   **Deployment:** Docker, Apache

## 2. API Documentation

### Base URL

*   `/cosmos`

### Authentication

All endpoints (except for health checks and documentation) require an API key to be passed in the `X-API-KEY` header.

### Endpoints

#### Products

*   `GET /products`: Retrieves a paginated list of all products.
*   `GET /products/search`: Searches for products based on a query.
*   `GET /products/{key}`: Retrieves a single product by its ID or handle.
*   `GET /products/{idOrHandle}/related`: Retrieves a list of related products.

#### Collections

*   `GET /collections/{handle}`: Retrieves a list of products belonging to a specific collection. Valid handles are: `all`, `featured`, `sale`, `new`, `bestsellers`, `trending`.

#### Image Proxy

*   `GET /image-proxy`: Proxies and caches images from a given URL.

#### Health & Documentation

*   `GET /health`: Provides a health check of the API and its dependencies.
*   `GET /ping`: A simple endpoint to check if the API is running.
*   `GET /version`: Returns the API version information.
*   `GET /docs`: Serves the Swagger UI for the API documentation.
*   `GET /docs/json`: Returns the OpenAPI 3.0 specification in JSON format.

### Response Formats

The API supports both JSON (default) and MessagePack formats. The format can be specified using the `format` query parameter (e.g., `?format=msgpack`).

## 3. Development Plan

The development of the API is divided into five phases:

*   **Phase 1: OpenAPI Documentation (Completed):** The API is fully documented using the OpenAPI specification.
*   **Phase 2: Input Validation (In Progress):** A validation system has been implemented, but there are some remaining issues to be addressed.
*   **Phase 3: Testing (Planned):** Unit and API tests are planned to be written.
*   **Phase 4: Performance Optimization (Planned):** Caching and database optimization are planned to improve performance.
*   **Phase 5: Deployment (Planned):** The infrastructure for deployment, including Docker setup and a CI/CD pipeline, is planned.

**Known Issues:**

*   Handling of the `msgpack_pack` function when the extension is not available.
*   The `$container` property in `ApiController` needs to be implemented.
*   The `getPrecomputedRelated` method needs to be fully integrated.
*   An unassigned `$params` variable in `ApiController.php` needs to be investigated.

## 4. Integration Guide

The `INTEGRATION.md` file provides a guide on how to integrate the Products API with a Next.js application using server-side rendering (SSR). It includes examples of how to fetch a list of products and a single product.

## 5. Local Development

The `WARP.md` file provides guidance for developers on how to work with the codebase. It includes common commands for:

*   Installing dependencies (`composer install`)
*   Running tests (`composer test`)
*   Linting code (`./vendor/bin/phpcs --standard=PSR12 src tests`)
*   Generating OpenAPI documentation (`composer docs`)
*   Serving the application locally using PHP's built-in server or Docker.

**Environment Variables:**

The application can be configured using environment variables for the database file, API key, CORS allowed origins, and Redis connection details. An `env.sample` file is provided as a template.
