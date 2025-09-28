<?php

namespace App\Services;

use App\Database\Database;
use App\Models\Product;
use PDO;

class ProductService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getProducts(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("SELECT * FROM products LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getTotalProducts(): int
    {
        $totalStmt = $this->db->query("SELECT COUNT(*) FROM products");
        return $totalStmt->fetchColumn();
    }

    public function searchProducts(string $query, string $selectFields = '*'): array
    {
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
        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getProductOrHandle(string $key): ?Product
    {
        if (is_numeric($key) && ctype_digit($key)) {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :key");
            $stmt->bindValue(':key', (int)$key, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE handle = :key");
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Product::class);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function getCollectionProducts(string $collectionHandle, int $page, int $limit, string $fieldsParam): array
    {
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
                return [];
        }
        
        // --- Fields Selection Logic ---
        $selectFields = '*';
        if (!empty($fieldsParam)) {
            $requestedFields = array_map('trim', explode(',', $fieldsParam));
            $validFields = array_intersect($requestedFields, Product::ALLOWED_PRODUCT_FIELDS);

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
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, Product::class);
    }

    public function getTotalCollectionProducts(string $collectionHandle): int
    {
        $whereClause = '1=1';
        
        // --- Collection Logic ---
        switch ($collectionHandle) {
            case 'all':
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                break;
            case 'sale':
                $whereClause = "compare_at_price IS NOT NULL AND compare_at_price > price";
                break;
            case 'new':
                $whereClause = '1=1';
                break;
            case 'bestsellers':
                $whereClause = '1=1';
                break;
            case 'trending':
                $whereClause = '1=1';
                break;
            default:
                return 0;
        }

        $totalStmt = $this->db->query("SELECT COUNT(*) FROM products WHERE {$whereClause}");
        return $totalStmt->fetchColumn();
    }
}
