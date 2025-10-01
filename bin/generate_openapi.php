#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Suppress vendor logger warnings during generation (we only care about the final JSON)
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_USER_WARNING & ~E_DEPRECATED);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Ignore warnings from swagger-php DefaultLogger
    if (strpos($errfile, 'zircote/swagger-php') !== false) {
        return true; // handled
    }
    // Let other errors proceed with default behavior
    return false;
});

use OpenApi\Generator;

$sourceDirs = [__DIR__ . '/../src'];

try {
    $gen = new \OpenApi\Generator();
    $gen->setAnalyser(new \OpenApi\Analysers\TokenAnalyser());
    $openapi = $gen->scan($sourceDirs);
    $outFile = __DIR__ . '/../public/openapi.json';
    if (!is_dir(dirname($outFile))) {
        mkdir(dirname($outFile), 0775, true);
    }
    file_put_contents($outFile, $openapi->toJson(JSON_PRETTY_PRINT));
    fwrite(STDOUT, "Generated $outFile\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "OpenAPI generation failed: " . $e->getMessage() . "\n");
    exit(1);
}
