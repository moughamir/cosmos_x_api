<?php
namespace App\Services;

use PDO;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class ProductProcessor
{
    private PDO $db;
    private string $jsonFilePath;

    public function __construct(string $jsonFilePath, array $config)
    {
        $this->jsonFilePath = $jsonFilePath;
        $this->setupDatabase($config['db_file']);
    }

    private function setupDatabase(string $dbFile): void
    {
        echo "--> [SETUP] Starting database setup...\n";
        $dbDir = dirname($dbFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        $this->db = new PDO("sqlite:" . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("DROP TABLE IF EXISTS products");
        $this->db->exec("DROP TABLE IF EXISTS products_fts");

        $this->db->exec("
            CREATE TABLE products (
                id INTEGER PRIMARY KEY,
                title TEXT,
                body_html TEXT,
                vendor TEXT,
                product_type TEXT,
                created_at TEXT,
                updated_at TEXT,
                published_at TEXT,
                source_domain TEXT,
                source_url TEXT,
                price REAL
            )
        ");
        $this->db->exec("
            CREATE VIRTUAL TABLE products_fts USING fts5(
                title, 
                body_html,
                product_type,
                content='products'
            )
        ");
        echo "--> [SETUP] Database setup complete. Tables 'products' and 'products_fts' created.\n";
    }

    /**
     * The Generator that uses native PHP functions to stream products 
     * character-by-character to bypass I/O stalls and corruption issues.
     */
    private function streamProductsFromFile(string $jsonFilePath): \Generator
    {
        if (!file_exists($jsonFilePath)) {
            throw new \Exception("Product JSON file not found at: " . $jsonFilePath);
        }

        $fileHandle = fopen($jsonFilePath, 'r');
        if ($fileHandle === false) {
            throw new \Exception("Could not open JSON file for reading.");
        }

        echo "--> [STREAM] Starting native line-by-line streaming...\n";

        $inProductsArray = false;

        // Skip the main object wrapper (e.g., {"products": [ )
        while (!feof($fileHandle)) {
            $line = fgets($fileHandle);
            if (strpos($line, '"products"') !== false || strpos(trim($line), '[') === 0) {
                $inProductsArray = true;
                break;
            }
        }

        if (!$inProductsArray) {
            throw new \Exception("Could not find the start of the 'products' array in the JSON file.");
        }

        // Now stream product objects character-by-character
        $productBuffer = '';
        $openBraceCount = 0;
        $productCount = 0;
        $allowedControlChars = ["\r", "\n", "\t"];

        while (!feof($fileHandle)) {
            $char = fgetc($fileHandle); // Read character by character for resilience
            if ($char === false)
                break;

            // FIX 1: Skip illegal ASCII control characters (0-31, except allowed ones)
            if (ord($char) < 32 && !in_array($char, $allowedControlChars) && $char !== ' ') {
                echo "--> [CLEANUP] Skipping illegal ASCII control character (ASCII " . ord($char) . ").\n";
                continue;
            }

            $productBuffer .= $char;

            if ($char === '{') {
                $openBraceCount++;
            } elseif ($char === '}') {
                $openBraceCount--;

                if ($openBraceCount === 0) {
                    $productCount++;

                    $jsonObject = trim(trim($productBuffer), ",\r\n");

                    if (!empty($jsonObject) && $jsonObject !== ']') {

                        // FIX 2: Aggressively strip control characters
                        // Matches all control characters using Unicode property and removes them.
                        $jsonObject = preg_replace('/[[:cntrl:]]/u', '', $jsonObject);

                        // FIX 3: FINAL BRUTE FORCE CLEANUP (Two-pass ICONV)
                        // This strips out any invalid UTF-8 character sequences.

                        // Pass A: Convert to ASCII, translating characters where possible and removing invalid ones (//IGNORE)
                        // This standardizes the string encoding by removing anything that isn't a simple character.
                        $cleanJson = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $jsonObject);

                        // Pass B: Convert back to UTF-8, ensuring final string is valid UTF-8
                        $jsonObject = iconv('ASCII', 'UTF-8//IGNORE', $cleanJson);

                        // Ensure we didn't end up with a null/empty string after the brute force clean
                        if (empty(trim($jsonObject))) {
                            echo "--> [CLEANUP] Product #{$productCount} was entirely stripped by cleanup. Skipping.\n";
                            continue;
                        }


                        $product = json_decode($jsonObject, true);

                        if ($product && json_last_error() === JSON_ERROR_NONE) {
                            yield $product;
                        } else {
                            // Log and skip malformed product object
                            echo "--> [STREAM ERROR] Failed to decode product JSON object #{$productCount}. Error: " . json_last_error_msg() . "\n";
                        }
                    }

                    $productBuffer = ''; // Reset buffer for the next product
                }
            }
        }

        fclose($fileHandle);
        echo "--> [STREAM] Native streaming finished. Total items yielded: {$productCount}.\n";
    }

    private function insertProduct(array $product): void
    {
        // Preparation and insertion logic
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO products (
                id, title, body_html, vendor, product_type, created_at, updated_at, published_at, source_domain, source_url, price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $product['id'] ?? null,
            $product['title'] ?? '',
            $product['body_html'] ?? '',
            $product['vendor'] ?? '',
            $product['product_type'] ?? '',
            $product['created_at'] ?? null,
            $product['updated_at'] ?? null,
            $product['published_at'] ?? null,
            $product['source_domain'] ?? 'local_file',
            $product['source_url'] ?? '',
            $product['price'] ?? 0
        ]);

        $ftsStmt = $this->db->prepare("
            INSERT INTO products_fts (
                rowid, title, body_html, product_type
            ) VALUES (?, ?, ?, ?)
        ");
        $ftsStmt->execute([
            $product['id'] ?? null,
            $product['title'] ?? '',
            $product['body_html'] ?? '',
            $product['product_type'] ?? ''
        ]);
    }

    private function applyPricingLogic(): void
    {
        echo "--> [PRICING] Starting pricing logic and filtering...\n";

        $stmt = $this->db->query("SELECT id, price FROM products ORDER BY price ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalProducts = count($products);
        echo "--> [PRICING] Loaded {$totalProducts} records for pricing operations.\n";

        $updateStmt = $this->db->prepare("UPDATE products SET price = ? WHERE id = ?");
        $deleteStmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $deletedCount = 0;

        foreach ($products as $product) {
            $adjustedPrice = $product['price'] * 0.4;

            if ($adjustedPrice < 300) {
                $deleteStmt->execute([$product['id']]);
                $deletedCount++;
                continue;
            }

            if ($adjustedPrice > 10000) {
                $adjustedPrice = 7999;
            }

            $updateStmt->execute([$adjustedPrice, $product['id']]);
        }
        echo "--> [PRICING] Pricing logic complete. Removed {$deletedCount} products below \$300.\n";
    }

    public function process(): array
    {
        $domains = [];
        $productTypes = [];
        $productCount = 0;

        echo "--> [DB] Starting database transaction...\n";
        $this->db->beginTransaction();

        try {
            $productStream = $this->streamProductsFromFile($this->jsonFilePath);

            foreach ($productStream as $product) {
                // Determine the price and insert logic...
                if (!empty($product['variants']) && is_array($product['variants'])) {
                    $prices = array_column($product['variants'], 'price');
                    $product['price'] = !empty($prices) ? min(array_map('floatval', $prices)) : 0;
                } else {
                    $product['price'] = floatval($product['price'] ?? 0);
                }

                $this->insertProduct($product);
                $productCount++;

                // Log every product inserted
                $productTitle = substr($product['title'] ?? 'N/A', 0, 50) . (strlen($product['title'] ?? '') > 50 ? '...' : '');
                echo "--> [INSERT] Product #{$productCount}: ID={$product['id']} Title='{$productTitle}'\n";

                // Track all unique product types and domains for final output
                if (!empty($product['product_type']) && !in_array($product['product_type'], $productTypes)) {
                    $productTypes[] = $product['product_type'];
                }
                if (!empty($product['source_domain']) && !in_array($product['source_domain'], $domains)) {
                    $domains[] = $product['source_domain'];
                }
            }

            echo "--> [DB] Finished inserting all {$productCount} initial records. Committing transaction...\n";
            $this->db->commit();
            echo "--> [DB] Transaction committed successfully.\n";
        } catch (\Exception $e) {
            echo "--> [ERROR] Processing failed. Rolling back transaction...\n";
            $this->db->rollBack();
            throw $e;
        }

        $this->applyPricingLogic();

        $stmt = $this->db->query("SELECT COUNT(*) FROM products");
        $totalProducts = $stmt->fetchColumn();

        return [
            'total_products' => $totalProducts,
            'domains' => $domains,
            'product_types' => $productTypes
        ];
    }
}
