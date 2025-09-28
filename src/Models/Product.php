<?php

namespace App\Models;

class Product
{
    public int $id;
    public string $name;
    public string $handle;
    public ?string $body_html;
    public float $price;
    public ?float $compare_at_price;
    public ?string $category;
    public bool $in_stock;
    public ?float $rating;
    public ?int $review_count;
    public ?string $tags;
    public ?string $vendor;
    public ?float $bestseller_score;
    public ?string $raw_json;
    public array $images = [];
}
