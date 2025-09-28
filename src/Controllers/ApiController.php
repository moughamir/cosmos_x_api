<?php
// src/Controllers/ApiController.php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Utils\MsgPackResponse; // Assuming you have this class for MsgPack
use PDO;

class ApiController
{
    private PDO $db;
    
    // Define the list of valid columns for security and the &fields= parameter
    private const ALLOWED_PRODUCT_FIELDS = [
        'id', 'name', 'handle', 'body_html', 'price', 'compare_at_price', 
        'category', 'in_stock', 'rating', 'review_count', 'tags', 'vendor', 
        'bestseller_score', // New calculated score
        'raw_json'
    ];

    public function __construct(string $dbFile)
    {
        $this->db = new PDO("sqlite:" . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Fetches images for a single product ID.
     * @param int $productId
     * @return array
     */
    private function getProductImages(int $productId): array
    {
        // Select all required fields from the product_images table
        $sql = "SELECT id, product_id, position, src, width, height, created_at, updated_at 
                FROM product_images 
                WHERE product_id = :product_id 
                ORDER BY position ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            // Assumes App\Utils\MsgPackResponse exists
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

        $totalStmt = $this->db->query("SELECT COUNT(*) FROM products");
        $total = $totalStmt->fetchColumn();

        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("SELECT * FROM products LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($products)) {
            // Handle empty product list
            return $this->outputResponse($response, ['products' => []], $format);
        }

        // --- NEW: Fetch All Related Images in a Single Query ---
        $productIds = array_column($products, 'id');
        $idString = implode(',', $productIds);
        
        $sqlImages = "SELECT product_id, id, position, src, width, height 
                      FROM product_images 
                      WHERE product_id IN ({$idString}) 
                      ORDER BY product_id, position ASC";
        
        $stmtImages = $this->db->query($sqlImages);
        $allImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

        // Map images back to their respective products
        $imagesByProductId = [];
        foreach ($allImages as $img) {
            $imagesByProductId[$img['product_id']][] = $img;
        }

        // --- Final Assembly ---
        foreach ($products as &$product) {
            $product['images'] = $imagesByProductId[$product['id']] ?? [];
            // Add other fields (variants, options) here as needed
        }
        unset($product); // Unset reference

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
        
        $sql = "SELECT {$selectFields} 
                FROM products 
                WHERE id IN (
                    SELECT rowid 
                    FROM products_fts 
                    WHERE products_fts MATCH :query
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $query);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($products)) {
            // --- NEW: Fetch All Related Images in a Single Query ---
            $productIds = array_column($products, 'id');
            $idString = implode(',', $productIds);
            
            $sqlImages = "SELECT product_id, id, position, src, width, height 
                          FROM product_images 
                          WHERE product_id IN ({$idString}) 
                          ORDER BY product_id, position ASC";
            
            $stmtImages = $this->db->query($sqlImages);
            $allImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

            // Map images back to their respective products
            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img['product_id']][] = $img;
            }

            // --- Final Assembly ---
            foreach ($products as &$product) {
                $product['images'] = $imagesByProductId[$product['id']] ?? [];
            }
            unset($product); // Unset reference
        }

        $data = ['products' => $products];
        return $this->outputResponse($response, $data, $format);
    }

    // --- 3. Get Single Product (ID or Handle) ---
    public function getProductOrHandle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';

        // Determine if key is numeric ID or string Handle
        if (is_numeric($key) && ctype_digit($key)) {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :key");
            $stmt->bindValue(':key', (int)$key, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE handle = :key");
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        }
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            $response = $response->withStatus(500);
            $response->getBody()->write(json_encode(['error' => 'Database Query Error. Check if the "handle" column exists in the products table: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // --- NEW: Attach Images, Variants, and Options ---
        $product['images'] = $this->getProductImages((int)$product['id']);
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

        $whereClause = '1=1';
        $orderBy = 'id ASC';
        $isPaginated = true;
        
        // --- Collection Logic ---
        switch ($collectionHandle) {
            case 'all':
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                $orderBy = 'RANDOM()';
                $limit = 8; // Featured list is small and non-paginated
                $isPaginated = false;
                break;
            case 'sale':
                $whereClause = "compare_at_price IS NOT NULL AND compare_at_price > price";
                $orderBy = 'price ASC';
                break;
            case 'new':
                $whereClause = '1=1';
                $orderBy = 'id DESC';
                break;
            case 'bestsellers':
                // Uses the pre-calculated weighted score
                $whereClause = '1=1';
                $orderBy = 'bestseller_score DESC, id DESC';
                break;
            case 'trending':
                $whereClause = '1=1';
                $orderBy = 'price DESC, id DESC'; // Heuristic
                break;
            default:
                $response = $response->withStatus(404);
                $response->getBody()->write(json_encode(['error' => "Collection handle '{$collectionHandle}' not found."]));
                return $response->withHeader('Content-Type', 'application/json');
        }
        
        // --- Fields Selection Logic ---
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
        
        // --- Build and Execute Query ---
        $offset = $isPaginated ? ($page - 1) * $limit : 0;
        
        $sql = "SELECT {$selectFields} 
                FROM products 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT :limit 
                OFFSET :offset";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $response = $response->withStatus(500);
            $response->getBody()->write(json_encode(['error' => 'Database Query Error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        if (!empty($products)) {
            // --- NEW: Fetch All Related Images in a Single Query ---
            $productIds = array_column($products, 'id');
            $idString = implode(',', $productIds);
            
            $sqlImages = "SELECT product_id, id, position, src, width, height 
                          FROM product_images 
                          WHERE product_id IN ({$idString}) 
                          ORDER BY product_id, position ASC";
            
            $stmtImages = $this->db->query($sqlImages);
            $allImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

            // Map images back to their respective products
            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img['product_id']][] = $img;
            }

            // --- Final Assembly ---
            foreach ($products as &$product) {
                $product['images'] = $imagesByProductId[$product['id']] ?? [];
            }
            unset($product); // Unset reference
        }

        // --- Handle Metadata ---
        $data = ['products' => $products];
        if ($isPaginated) {
            // Only count total rows if paginated
            $totalStmt = $this->db->query("SELECT COUNT(id) FROM products WHERE {$whereClause}");
            $total = $totalStmt->fetchColumn();

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