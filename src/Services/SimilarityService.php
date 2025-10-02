<?php

namespace App\Services;

use PDO;

class SimilarityService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find similar products based on various attributes
     * 
     * @param int $productId The ID of the product to find similar items for
     * @param int $limit Maximum number of similar products to return
     * @return array Array of similar product IDs with similarity scores
     */
    public function findSimilarProducts(int $productId, int $limit = 5): array
    {
        // Default implementation - can be enhanced with more sophisticated algorithms
        // This is a simple implementation that finds products in the same category
        
        $stmt = $this->db->prepare("
            SELECT p2.id, 
                   COUNT(DISTINCT p2.id) as score
            FROM products p1
            JOIN products p2 ON p1.category_id = p2.category_id
            WHERE p1.id = :product_id
            AND p2.id != :product_id
            GROUP BY p2.id
            ORDER BY score DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate similarity score between two products
     * 
     * @param int $productId1 First product ID
     * @param int $productId2 Second product ID
     * @return float Similarity score (0-1)
     */
    public function calculateSimilarity(int $productId1, int $productId2): float
    {
        // Simple implementation - can be enhanced with more sophisticated algorithms
        // This just checks if products are in the same category
        
        $stmt = $this->db->prepare("
            SELECT 
                (SELECT category_id FROM products WHERE id = :id1) as cat1,
                (SELECT category_id FROM products WHERE id = :id2) as cat2
        ");
        
        $stmt->execute([
            ':id1' => $productId1,
            ':id2' => $productId2
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && $result['cat1'] === $result['cat2']) ? 1.0 : 0.0;
    }

    public function getPrecomputedRelated(int $productId, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT p.* FROM products p
            JOIN product_similarities s ON p.id = s.target_id
            WHERE s.source_id = :product_id
            ORDER BY s.score DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, \App\Models\Product::class);
    }
}
