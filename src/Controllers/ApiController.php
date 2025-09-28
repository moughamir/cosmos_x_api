<?php
// src/Controllers/ApiController.php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\MsgPackResponse;
use App\Database\Database;
use App\Models\Product;
use App\Services\ProductService;
use PDO;

class ApiController
{
    private ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }



    /**
     * Fetches images for a single product ID.
     * @param int $productId
     * @return array
     */
    private function getProductImages(int $productId): array
    {
        $db = Database::getInstance();
        // Select all required fields from the product_images table
        $sql = "SELECT id, product_id, position, src, width, height, created_at, updated_at 
                FROM product_images 
                WHERE product_id = :product_id 
                ORDER BY position ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        
        $images = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Image');

        // Convert string nulls/empty strings to actual PHP nulls/proper types if necessary
        // In this case, basic associative array is sufficient for simple fields.
        
        return $images;
    }

    private function outputResponse(Response $response, array $data, string $format = 'json'): Response
    {
        if ($format === 'msgpack') {
            if (!extension_loaded('msgpack')) {
                // Fallback to JSON if extension is missing
                $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
                return $response->withHeader('Content-Type', 'application/json');
            }
            // Assumes App\Models\MsgPackResponse exists
            return MsgPackResponse::withMsgPack($response, $data); 
        } else {
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    // --- 1. Get All Products (Paginated) ---
    public function getProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $format = $params['format'] ?? 'json';

        $products = $this->productService->getProducts($page, $limit);
        $total = $this->productService->getTotalProducts();

        if (empty($products)) {
            // Handle empty product list
            return $this->outputResponse($response, ['products' => []], $format);
        }

        // --- NEW: Fetch All Related Images in a Single Query ---
        $productIds = array_map(fn($p) => $p->id, $products);
        $idString = implode(',', $productIds);
        
        $db = Database::getInstance();
        $sqlImages = "SELECT product_id, id, position, src, width, height 
                      FROM product_images 
                      WHERE product_id IN ({$idString}) 
                      ORDER BY product_id, position ASC";
        
        $stmtImages = $db->query($sqlImages);
        $allImages = $stmtImages->fetchAll(PDO::FETCH_CLASS, 'App\Models\Image');

        // Map images back to their respective products
        $imagesByProductId = [];
        foreach ($allImages as $img) {
            $imagesByProductId[$img->product_id][] = $img;
        }

        // --- Final Assembly ---
        foreach ($products as $product) {
            $product->images = $imagesByProductId[$product->id] ?? [];
        }

        $data = [
            'products' => $products,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];

        return $this->outputResponse($response, $data, $format);
    }

    // --- 2. Search Products (FTS) ---
    public function searchProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';

        // Fields Selection Logic
        $selectFields = '*';
        if (!empty($fieldsParam)) {
            $requestedFields = array_map('trim', explode(',', $fieldsParam));
            $validFields = array_intersect($requestedFields, self::ALLOWED_PRODUCT_FIELDS);

            if (!empty($validFields)) {
                if (!in_array('id', $validFields)) {
                    $validFields[] = 'id';
                }
                $selectFields = implode(', ', $validFields);
            }
        }

        $products = $this->productService->searchProducts($query, $selectFields);

        if (!empty($products)) {
            // --- NEW: Fetch All Related Images in a Single Query ---
            $productIds = array_map(fn($p) => $p->id, $products);
            $idString = implode(',', $productIds);
            
            $db = Database::getInstance();
            $sqlImages = "SELECT product_id, id, position, src, width, height 
                          FROM product_images 
                          WHERE product_id IN ({$idString}) 
                          ORDER BY product_id, position ASC";
            
            $stmtImages = $db->query($sqlImages);
            $allImages = $stmtImages->fetchAll(PDO::FETCH_CLASS, 'App\Models\Image');

            // Map images back to their respective products
            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img->product_id][] = $img;
            }

            // --- Final Assembly ---
            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }

        $data = ['products' => $products];
        return $this->outputResponse($response, $data, $format);
    }

    // --- 3. Get Single Product (ID or Handle) ---
    public function getProductOrHandle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $format = $request->getQueryParams()['format'] ?? 'json';

        $product = $this->productService->getProductOrHandle($key);

        if (!$product) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // --- NEW: Attach Images, Variants, and Options ---
        $product->images = $this->getProductImages($product->id);
        // You would call getProductVariants() and getProductOptions() here too.
        
        $data = ['product' => $product];
        return $this->outputResponse($response, $data, $format);
    }

    // --- 4. Get Collection Products (/collections/{handle}) ---
    public function getCollectionProducts(Request $request, Response $response, array $args): Response
    {
        $collectionHandle = strtolower($args['handle']);
        $params = $request->getQueryParams();
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));

        $products = $this->productService->getCollectionProducts($collectionHandle, $page, $limit, $fieldsParam);

        if (!empty($products)) {
            // --- NEW: Fetch All Related Images in a Single Query ---
            $productIds = array_map(fn($p) => $p->id, $products);
            $idString = implode(',', $productIds);
            
            $db = Database::getInstance();
            $sqlImages = "SELECT product_id, id, position, src, width, height 
                          FROM product_images 
                          WHERE product_id IN ({$idString}) 
                          ORDER BY product_id, position ASC";
            
            $stmtImages = $db->query($sqlImages);
            $allImages = $stmtImages->fetchAll(PDO::FETCH_CLASS, 'App\Models\Image');

            // Map images back to their respective products
            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img->product_id][] = $img;
            }

            // --- Final Assembly ---
            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }

        // --- Handle Metadata ---
        $data = ['products' => $products];
        if ($collectionHandle !== 'featured') { // featured is not paginated
            $total = $this->productService->getTotalCollectionProducts($collectionHandle);
            $data['meta'] = [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];
        }

        return $this->outputResponse($response, $data, $format);
    }
}
