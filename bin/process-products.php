#!/usr/bin/env php

<?php
// process-products.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ProductProcessor;
use PDO;

$config = require __DIR__ . '/../config/app.php';

// Define the path to your locally saved products.json file
$jsonFilePath = __DIR__ . '/../data/products.json';

// IMPORTANT: Ensure your products.json is saved in the /cosmos/data/ directory, 
// or update the path above to point to the correct location.

try {
    // Pass the file path to the processor instead of the array of endpoints
    $processor = new ProductProcessor($jsonFilePath, $config);
    $result = $processor->process();

    echo "\n=== Processing Complete ===\n";
    echo "Total products: " . $result['total_products'] . "\n";
    echo "Domains Processed (from file data): " . implode(', ', $result['domains']) . "\n";
    echo "Product types: " . count($result['product_types']) . "\n";

    // Query the database to get the counts for each product type
    $db = new PDO("sqlite:" . $config['db_file']);
    $stmt = $db->query("SELECT product_type, COUNT(*) as count FROM products GROUP BY product_type");
    $productTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n--- Products by Type ---\n";
    foreach ($productTypes as $type) {
        echo "- " . ($type['product_type'] ?: 'Unknown') . ": " . $type['count'] . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
