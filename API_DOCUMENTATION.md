
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
    - `page` (optional, integer, default: 1): The page number to retrieve.
    - `limit` (optional, integer, default: 50, max: 100): The number of products to retrieve per page.
    - `format` (optional, string, default: 'json'): The response format. Can be `json` or `msgpack`.
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
    - `q` (required, string): The search query.
    - `fields` (optional, string): A comma-separated list of fields to retrieve.
    - `format` (optional, string, default: 'json'): The response format. Can be `json` or `msgpack`.
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
    - `fields` (optional, string): A comma-separated list of fields to retrieve.
    - `format` (optional, string, default: 'json'): The response format. Can be `json` or `msgpack`.
    - `page` (optional, integer, default: 1): The page number to retrieve.
    - `limit` (optional, integer, default: 50, max: 100): The number of products to retrieve per page.
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
