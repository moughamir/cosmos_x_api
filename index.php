<?php

try {
    require __DIR__ . '/vendor/autoload.php';
} catch (\Throwable $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
    exit;
}

$app = App\App::bootstrap();
$app->run();