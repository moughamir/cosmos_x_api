<?php

namespace App\Models;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Variant",
 *     type="object",
 *     title="Variant",
 *     description="Product variant",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="sku", type="string"),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="compare_at_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="available", type="boolean"),
 *     @OA\Property(property="options", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     title="Product",
 *     description="Product entity",
 *     @OA\Property(property="id", type="integer", format="int64", description="Unique identifier"),
 *     @OA\Property(property="name", type="string", description="Product name"),
 *     @OA\Property(property="handle", type="string", description="Product handle"),
 *     @OA\Property(property="body_html", type="string", description="Product description"),
 *     @OA\Property(property="price", type="number", format="float", description="Product price"),
 *     @OA\Property(property="compare_at_price", type="number", format="float", description="Product compare at price", nullable=true),
 *     @OA\Property(property="category", type="string", description="Product category", nullable=true),
 *     @OA\Property(property="in_stock", type="boolean", description="Is the product in stock (always true)"),
 *     @OA\Property(property="rating", type="number", format="float", description="Product rating", nullable=true),
 *     @OA\Property(property="review_count", type="integer", description="Product review count", nullable=true),
 *     @OA\Property(property="tags", type="string", description="Product tags", nullable=true),
 *     @OA\Property(property="vendor", type="string", description="Product vendor", nullable=true),
 *     @OA\Property(property="bestseller_score", type="number", format="float", description="Product bestseller score", nullable=true),
 *     @OA\Property(property="images", type="array", @OA\Items(ref="#/components/schemas/Image")),
 *     @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/Variant"))
 * )
 */
class Product
{
    public const ALLOWED_PRODUCT_FIELDS = [
        'id',
        'name',
        'handle',
        'body_html',
        'price',
        'compare_at_price',
        'category',
        'in_stock',
        'rating',
        'review_count',
        'tags',
        'vendor',
        'bestseller_score',
        'raw_json'
    ];

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
    public array $variants = [];
}
