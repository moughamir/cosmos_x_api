<?php

namespace App\Models;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="Image",
 *   type="object",
 *   @OA\Property(property="id", type="integer"),
 *   @OA\Property(property="product_id", type="integer"),
 *   @OA\Property(property="position", type="integer"),
 *   @OA\Property(property="src", type="string", description="Rewritten CDN URL (moritotabi.com/cdn)"),
 *   @OA\Property(property="width", type="integer", nullable=true),
 *   @OA\Property(property="height", type="integer", nullable=true),
 *   @OA\Property(property="created_at", type="string", nullable=true),
 *   @OA\Property(property="updated_at", type="string", nullable=true)
 * )
 */
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
