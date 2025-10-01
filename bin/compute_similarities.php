#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use PDO;

$dbFile = getenv('DB_FILE') ?: __DIR__ . '/../config/data/sqlite/products.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tables
$pdo->exec("CREATE TABLE IF NOT EXISTS product_similarities (
    source_id INTEGER NOT NULL,
    target_id INTEGER NOT NULL,
    score REAL NOT NULL,
    method TEXT NOT NULL DEFAULT 'fts_mix',
    updated_at TEXT NOT NULL,
    PRIMARY KEY (source_id, target_id)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_similarities_source ON product_similarities(source_id)");

// Load product ids and basic features
$products = $pdo->query("SELECT id, name, tags, category, vendor, price FROM products ORDER BY id ASC")
                ->fetchAll(PDO::FETCH_ASSOC);

if (!$products) {
    fwrite(STDERR, "No products found.\n");
    exit(0);
}

$now = (new DateTimeImmutable())->format('c');

$insert = $pdo->prepare("INSERT INTO product_similarities (source_id, target_id, score, method, updated_at)
                         VALUES (:source, :target, :score, :method, :updated)
                         ON CONFLICT(source_id, target_id) DO UPDATE SET score=excluded.score, method=excluded.method, updated_at=excluded.updated_at");

$batchLimit = (int)(getenv('SIM_LIMIT_PER_PRODUCT') ?: 20);
$hasFts = false;
try {
    $pdo->query("SELECT 1 FROM products_fts LIMIT 1");
    $hasFts = true;
} catch (Throwable $e) { $hasFts = false; }

foreach ($products as $p) {
    $sourceId = (int)$p['id'];
    $name = (string)($p['name'] ?? '');
    $tags = (string)($p['tags'] ?? '');
    $category = (string)($p['category'] ?? '');
    $vendor = (string)($p['vendor'] ?? '');
    $price = (float)($p['price'] ?? 0);

    // Seed candidates via FTS if available; otherwise pull a random window
    if ($hasFts && $name !== '') {
        // Use the name and category as a match query; escape quotes
        $q = str_replace('"', '""', trim($name . ' ' . $category));
        $stmt = $pdo->prepare("SELECT rowid AS id, bm25(products_fts) AS rank
                               FROM products_fts
                               WHERE products_fts MATCH :q AND rowid != :id
                               ORDER BY rank LIMIT :k");
        $stmt->bindValue(':q', $q);
        $stmt->bindValue(':id', $sourceId, PDO::PARAM_INT);
        $stmt->bindValue(':k', $batchLimit, PDO::PARAM_INT);
        $stmt->execute();
        $candidateIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    } else {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id != :id ORDER BY RANDOM() LIMIT :k");
        $stmt->bindValue(':id', $sourceId, PDO::PARAM_INT);
        $stmt->bindValue(':k', $batchLimit, PDO::PARAM_INT);
        $stmt->execute();
        $candidateIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    if (!$candidateIds) { continue; }

    // Fetch candidate rows
    $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
    $stmt = $pdo->prepare("SELECT id, tags, category, vendor, price, bestseller_score FROM products WHERE id IN ($placeholders)");
    foreach ($candidateIds as $i => $cid) { $stmt->bindValue($i+1, (int)$cid, PDO::PARAM_INT); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $srcTags = array_values(array_filter(array_map('trim', explode(',', $tags))));

    $scored = [];
    foreach ($rows as $row) {
        $score = 0.0;
        if ($vendor && $row['vendor'] === $vendor) $score += 5.0;
        if ($category && $row['category'] === $category) $score += 4.0;
        // tag overlap
        $pTags = array_values(array_filter(array_map('trim', explode(',', (string)$row['tags']))));
        $overlap = count(array_intersect($srcTags, $pTags));
        $score += $overlap * 2.0;
        // price proximity
        $price2 = (float)($row['price'] ?? 0);
        $penalty = ($price == 0.0) ? 0.0 : abs($price2 - $price) / $price;
        $score -= $penalty;
        // bestseller
        if ($row['bestseller_score'] !== null) { $score += ((float)$row['bestseller_score'])/10.0; }
        $scored[(int)$row['id']] = $score;
    }

    arsort($scored);
    $top = array_slice($scored, 0, $batchLimit, true);

    foreach ($top as $targetId => $score) {
        if ($targetId === $sourceId) continue;
        $insert->execute([
            ':source' => $sourceId,
            ':target' => (int)$targetId,
            ':score' => (float)$score,
            ':method' => $hasFts ? 'fts_mix' : 'heuristic',
            ':updated' => $now,
        ]);
    }
}

echo "Similarity table updated for " . count($products) . " products.\n";
