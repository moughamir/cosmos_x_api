<?php

namespace App\Services;

use App\Database\Database;
use App\Models\Image;
use PDO;

class ImageService
{
    public function getProductImages(int $productId): array
    {
        $db = Database::getInstance();
        $sql = "SELECT id, product_id, position, src, width, height, created_at, updated_at 
                FROM product_images 
                WHERE product_id = :product_id 
                ORDER BY position ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_CLASS, Image::class);
    }

    public function getImagesForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $db = Database::getInstance();
        $idString = implode(',', $productIds);
        
        $sqlImages = "SELECT product_id, id, position, src, width, height 
                      FROM product_images 
                      WHERE product_id IN ({$idString}) 
                      ORDER BY product_id, position ASC";
        
        $stmtImages = $db->query($sqlImages);
        return $stmtImages->fetchAll(PDO::FETCH_CLASS, Image::class);
    }
}
