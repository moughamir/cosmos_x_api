<?php

namespace App\Services;

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
            $stmt->bindValue(':key', (int) $key, PDO::PARAM_INT);
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

        switch ($collectionHandle) {
            case 'all':
                break;
            case 'featured':
                $whereClause = "tags LIKE '%featured%'";
                $orderBy = 'RANDOM()';
                $limit = 8;
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

                $whereClause = '1=1';
                $orderBy = 'bestseller_score DESC, id DESC';
                break;
            case 'trending':
                $whereClause = '1=1';
                $orderBy = 'price DESC, id DESC';
                break;
            default:
                return [];
        }

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

    /**
     * Populate variants field on a product by decoding raw_json->variants, if available.
     */
    public function attachVariantsToProduct(\App\Models\Product $product): void
    {
        $product->variants = [];
        if (empty($product->raw_json)) {
            return;
        }
        try {
            $data = json_decode($product->raw_json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || empty($data['variants']) || !is_array($data['variants'])) {
                return;
            }
            foreach ($data['variants'] as $v) {
                $product->variants[] = [
                    'id' => $v['id'] ?? null,
                    'title' => $v['title'] ?? null,
                    'sku' => $v['sku'] ?? null,
                    'price' => isset($v['price']) ? (float) $v['price'] : null,
                    'compare_at_price' => isset($v['compare_at_price']) ? (float) $v['compare_at_price'] : null,
                    'available' => array_key_exists('available', $v) ? (bool) $v['available'] : true,
                    'options' => $v['options'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // If decode fails, leave variants empty
        }
    }

    /**
     * Compute related products based on vendor, category, tag overlap, bestseller_score, and price proximity.
     */
    public function getRelatedProducts(int $productId, int $limit = 8): array
    {
        // Load base product signals
        $stmt = $this->db->prepare("SELECT vendor, category, tags, price FROM products WHERE id = :id");
        $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$base) {
            return [];
        }
        $vendor = $base['vendor'] ?? null;
        $category = $base['category'] ?? null;
        $price = (float) ($base['price'] ?? 0);
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($base['tags'] ?? '')))));
        $tags = array_slice($tags, 0, 5);

        $relSql = "SELECT p.*,
            (
                (CASE WHEN p.vendor = :vendor THEN 5 ELSE 0 END)
              + (CASE WHEN p.category = :category THEN 4 ELSE 0 END)
              + (CASE WHEN p.bestseller_score IS NOT NULL THEN (p.bestseller_score/10.0) ELSE 0 END)
            ) AS base_score,
            (ABS(p.price - :price) / CASE WHEN :price = 0 THEN 1 ELSE :price END) AS price_penalty
          FROM products p
          WHERE p.id != :id";

        $stmt2 = $this->db->prepare($relSql . " ORDER BY base_score - price_penalty DESC, p.id DESC LIMIT :limit");
        $stmt2->bindValue(':vendor', $vendor);
        $stmt2->bindValue(':category', $category);
        $stmt2->bindValue(':price', (string) $price);
        $stmt2->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt2->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt2->execute();
        $candidates = $stmt2->fetchAll(PDO::FETCH_CLASS, Product::class);

        // Post-process: tag overlap scoring
        if (!empty($tags)) {
            foreach ($candidates as $p) {
                $pTags = array_values(array_filter(array_map('trim', explode(',', (string) $p->tags))));
                $overlap = count(array_intersect($tags, $pTags));
                // approximate improvement to ranking by overlap
                $p->related_score = ($overlap * 2);
            }
            usort($candidates, function ($a, $b) {
                $sa = $a->related_score ?? 0;
                $sb = $b->related_score ?? 0;
                return $sb <=> $sa;
            });
            $candidates = array_slice($candidates, 0, $limit);
        }
        return $candidates;
    }
}
