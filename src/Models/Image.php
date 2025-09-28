<?php

namespace App\Models;

class Image
{
    public int $id;
    public int $product_id;
    public int $position;
    public string $src;
    public ?int $width;
    public ?int $height;
    public ?string $created_at;
    public ?string $updated_at;
}
