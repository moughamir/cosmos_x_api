
# API Documentation

This document provides a detailed overview of the API endpoints for the Products API.

## Base URL

The base URL for all API endpoints is `/cosmos`.

## Authentication

All endpoints require an API key for access. The API key must be provided in the `X-API-KEY` header of the request.

## Endpoints

### Products

#### 1. Get All Products

- **Endpoint:** `GET /products`
- **Description:** Retrieves a paginated list of all products.
- **Query Parameters:**
    - `page` (optional, integer, default: 1)
    - `limit` (optional, integer, default: 50, max: 100)
    - `include_variants` (optional, 0|1, default: 0) include parsed variants
    - `format` (optional, string, default: 'json'): `json` or `msgpack`
- **Example Response:**
  ```json
  {
    "products": [
      {
        "id": 1,
        "title": "Product 1",
        "handle": "product-1",
        "images": [
          {
            "id": 1,
            "product_id": 1,
            "src": "https://example.com/image1.jpg"
          }
        ]
      }
    ],
    "meta": {
      "total": 100,
      "page": 1,
      "limit": 50,
      "total_pages": 2
    }
  }
  ```

#### 2. Search Products

- **Endpoint:** `GET /products/search`
- **Description:** Searches for products based on a query.
- **Query Parameters:**
    - `q` (required, string)
    - `fields` (optional, string): comma-separated list of allowed fields
    - `include_variants` (optional, 0|1, default: 0)
    - `format` (optional, string, default: 'json'): `json` or `msgpack`
- **Example Response:**
  ```json
  {
    "products": [
      {
        "id": 1,
        "title": "Product 1",
        "handle": "product-1"
      }
    ]
  }
  ```

#### 3. Get Single Product

- **Endpoint:** `GET /products/{key}`
- **Description:** Retrieves a single product by its ID or handle.
- **Path Parameters:**
    - `key` (required, string): The ID or handle of the product.
- **Query Parameters:**
    - `format` (optional, string, default: 'json'): The response format. Can be `json` or `msgpack`.
- **Example Response:**
  ```json
  {
    "product": {
      "id": 1,
      "title": "Product 1",
      "handle": "product-1",
      "images": [
        {
          "id": 1,
          "product_id": 1,
          "src": "https://example.com/image1.jpg"
        }
      ]
    }
  }
  ```

### Collections

#### 1. Get Collection Products

- **Endpoint:** `GET /collections/{handle}`
- **Description:** Retrieves a list of products belonging to a specific collection.
- **Path Parameters:**
    - `handle` (required, string): The handle of the collection.
- **Query Parameters:**
    - `fields` (optional, string)
    - `include_variants` (optional, 0|1, default: 0)
    - `format` (optional, string, default: 'json')
    - `page` (optional, integer, default: 1)
    - `limit` (optional, integer, default: 50, max: 100)
- **Example Response:**
  ```json
  {
    "products": [
      {
        "id": 1,
        "title": "Product 1",
        "handle": "product-1"
      }
    ],
    "meta": {
      "total": 10,
      "page": 1,
      "limit": 50,
      "total_pages": 1
    }
  }
  ```

### Image Proxy

#### 1. Get Image

- **Endpoint:** `GET /image-proxy`
- **Description:** Proxies and caches images from a given URL.
- **Query Parameters:**
  - `url` (required, string): The URL of the image to proxy.
- **Notes:** Only these domains are allowed: cdn.shopify.com, shopify.com, cdn.moritotabi.com. URLs are rewritten to moritotabi.com/cdn.

---

## Comprehensive Guide

Base URL and context path
- Docker/Apache: http://localhost:8080/cosmos
- PHP built-in server: http://127.0.0.1:8080
- Configure via BASE_PATH (default /cosmos in containers; disabled for built-in server)

Authentication
- Add header: X-API-KEY: {{API_KEY}}
- Public: /health, /ping, /version, /docs, /docs/json

Formats
- JSON default; MessagePack available via ?format=msgpack (if msgpack ext loaded)

Images
- API rewrites cdn.shopify.com/* to https://moritotabi.com/cdn/* automatically

New/advanced endpoints and params
- include_variants=1 on list/search/collection endpoints to include product variants parsed from raw_json
- Related products: GET /cosmos/products/{idOrHandle}/related?limit=8

Related products (deep similarity)
- Precomputed table product_similarities(source_id, target_id, score, method, updated_at)
- Generate/update:
  - composer similarity:rebuild
  - or DB_FILE=/path/to/sqlite php bin/compute_similarities.php
- Runtime: API uses precomputed results first; falls back to heuristic if empty

Environment
- DB_FILE, API_KEY, BASE_PATH, CORS_ALLOWED_ORIGINS, REDIS_HOST/PORT/PASSWORD

Examples
- curl -H "X-API-KEY: {{API_KEY}}" "http://localhost:8080/cosmos/products?limit=20"
- curl -H "X-API-KEY: {{API_KEY}}" "http://localhost:8080/cosmos/products/search?q=shirt&fields=id,name,price"
- curl -H "X-API-KEY: {{API_KEY}}" "http://localhost:8080/cosmos/products/123/related?limit=8"
- curl -H "X-API-KEY: {{API_KEY}}" "http://localhost:8080/cosmos/collections/sale?page=1&limit=24"
