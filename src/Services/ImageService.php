<?php

namespace App\Services;

use App\Models\Image;
use PDO;

class ImageService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getProductImages(int $productId): array
    {
        $sql = "SELECT id, product_id, position, src, width, height, created_at, updated_at 
                FROM product_images 
                WHERE product_id = :product_id 
                ORDER BY position ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $images = $stmt->fetchAll(PDO::FETCH_CLASS, Image::class);
        foreach ($images as $img) {
            $img->src = $this->rewriteCdnUrl($img->src);
        }
        return $images;
    }

    public function getImagesForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $sqlImages = "SELECT product_id, id, position, src, width, height 
                      FROM product_images 
                      WHERE product_id IN ($placeholders) 
                      ORDER BY product_id, position ASC";

        $stmtImages = $this->db->prepare($sqlImages);
        foreach (array_values($productIds) as $i => $pid) {
            $stmtImages->bindValue($i + 1, (int) $pid, PDO::PARAM_INT);
        }
        $stmtImages->execute();
        $images = $stmtImages->fetchAll(PDO::FETCH_CLASS, Image::class);
        foreach ($images as $img) {
            $img->src = $this->rewriteCdnUrl($img->src);
        }
        return $images;
    }
    private function rewriteCdnUrl(string $url): string
    {
        // Normalize and rewrite cdn.shopify.com to moritotabi.com/cdn
        // Handles both http and https
        $patterns = [
            '#^https?://cdn\.shopify\.com#i',
        ];
        $replacement = 'https://moritotabi.com/cdn';
        foreach ($patterns as $pattern) {
            $url = preg_replace($pattern, $replacement, $url);
        }
        return $url;
    }
}
