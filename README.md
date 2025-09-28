# Products API

This project provides a simple, high-performance, read-only API for accessing product data. It is built with PHP and uses SQLite for data storage, making it lightweight and easy to set up.

## Features

- **Fast & Lightweight**: Built with a minimal footprint, ideal for quick lookups.
- **RESTful Endpoints**: Access products, collections, and search.
- **Flexible Output**: Supports both JSON and MsgPack formats.
- **FTS Search**: Full-text search enabled for product attributes.
- **Easy to Deploy**: No external database dependencies required.

## Endpoints

- `GET /products`: Retrieve a paginated list of all products.
- `GET /products/{id}`: Get a single product by its ID.
- `GET /products/handle/{handle}`: Get a single product by its handle.
- `GET /search?q={query}`: Search for products using a search term.
- `GET /collections/{handle}`: Retrieve products from a specific collection (e.g., `all`, `featured`, `sale`).

## Setup

1.  **Prerequisites**: PHP with the `pdo_sqlite` extension.
2.  **Installation**: Run `composer install` to install dependencies.
3.  **Database**: Place your `products.sqlite` database file in the `config/` directory.
4.  **Web Server**: Configure your web server (e.g., Nginx, Apache) to point to the `index.php` file and handle routing.
