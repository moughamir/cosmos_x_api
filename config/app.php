<?php
// config/app.php

return [
    // --- Database Configuration ---
    // Update this path if your SQLite file is stored elsewhere
    'db_file' => __DIR__ . '/data/sqlite/products.sqlite',
    
    // --- Security Configuration ---
    // !! IMPORTANT: Replace this with your actual, private API key
    'api_key' => '0x071aL3LA97incWZOgX3eEtO0PlRMTLxbxfPutmZALnjGS/Q=4cb2cb57=13f26539',
    
    // --- Image Proxy Configuration ---
    'image_proxy' => [
        'base_url' => 'https://cdn.shopify.com', // Example source domain
        'cache_dir' => __DIR__ . '/data/cache/images',
        'ttl' => 86400, // 24 hours cache time-to-live
    ]
];