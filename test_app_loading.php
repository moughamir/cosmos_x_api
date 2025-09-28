<?php
// test_app_loading.php - Test if App class loads without errors

require __DIR__ . '/vendor/autoload.php';

try {
    echo "Testing App class loading...\n";
    $app = App\App::bootstrap();
    echo "SUCCESS: App class loaded successfully!\n";
    echo "App type: " . get_class($app) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
