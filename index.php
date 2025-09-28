<?php
// index.php
require __DIR__ . '/vendor/autoload.php';

use App\App;

$app = App::bootstrap();
$app->run();