<?php
// setup_database.php - Create database tables

$databasePath = __DIR__ . '/config/data/sqlite/products.sqlite';

try {
    $pdo = new PDO("sqlite:" . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and execute schema.sql
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($schema);

    echo "Database tables created successfully!\n";

    // Verify tables exist
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Created tables: " . implode(', ', $tables) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
