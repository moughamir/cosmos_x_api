<?php

return [
    'api_key' => getenv('API_KEY'),
    'image_proxy' => [
        'base_url' => getenv('IMAGE_PROXY_BASE_URL') ?: 'https://cdn.shopify.com',
        'cache_dir' => __DIR__ . '/data/cache/images',
        'ttl' => (int) (getenv('IMAGE_CACHE_TTL') ?: 86400),
    ],
    'cors' => [
        'allowed_origins' => explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '*'),
    ],
    'rate_limit' => [
        'requests' => (int) (getenv('RATE_LIMIT_REQUESTS') ?: 100),
        'window' => (int) (getenv('RATE_LIMIT_WINDOW') ?: 3600),
    ],
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: 'localhost',
        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null,
    ]
];