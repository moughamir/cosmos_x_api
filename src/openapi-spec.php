<?php
use OpenApi\Annotations as OA;
use OpenApi\Generator;

// Generate the OpenAPI documentation
$openapi = Generator::scan([__DIR__]);

// Return the OpenAPI object
return $openapi;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="Cosmos Products API",
 *         description="A comprehensive API for managing and querying product information with support for JSON and MessagePack formats.",
 *         termsOfService="https://example.com/terms/",
 *         @OA\Contact(
 *             email="support@example.com",
 *             name="API Support",
 *             url="https://support.example.com"
 *         ),
 *         @OA\License(
 *             name="MIT",
 *             url="https://opensource.org/licenses/MIT"
 *         )
 *     ),
 *     @OA\Server(
 *         url="/cosmos",
 *         description="Development Server"
 *     ),
 *     @OA\Server(
 *         url="https://api.example.com",
 *         description="Production Server"
 *     ),
 *     @OA\ExternalDocumentation(
 *         description="Find more info here",
 *         url="https://docs.example.com"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Products",
 *     description="Operations about products",
 *     @OA\ExternalDocumentation(
 *         description="Find more about our products",
 *         url="https://example.com/docs/products"
 *     )
 * )
 * @OA\Tag(
 *     name="Collections",
 *     description="Product collections and categories"
 * )
 * @OA\Tag(
 *     name="Search",
 *     description="Search operations"
 * )
 * @OA\Tag(
 *     name="Health",
 *     description="Health check endpoints"
 * )
 * 
 * @OA\Components(
 *     @OA\SecurityScheme(
 *         securityScheme="api_key",
 *         type="apiKey",
 *         in="header",
 *         name="X-API-KEY",
 *         description="API key for accessing the API"
 *     ),
 *     @OA\Schema(
 *         schema="Error",
 *         title="Error Response",
 *         @OA\Property(property="error", type="object",
 *             @OA\Property(property="code", type="integer", format="int32"),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="details", type="object", nullable=true)
 *         )
 *     ),
 *     @OA\Schema(
 *         schema="Pagination",
 *         title="Pagination Metadata",
 *         @OA\Property(property="total", type="integer", example=100),
 *         @OA\Property(property="page", type="integer", example=1),
 *         @OA\Property(property="limit", type="integer", example=50),
 *         @OA\Property(property="total_pages", type="integer", example=2)
 *     ),
 *     @OA\Schema(
 *         schema="Product",
 *         title="Product",
 *         required={"id", "name", "price"},
 *         @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *         @OA\Property(property="name", type="string", example="Premium Widget"),
 *         @OA\Property(property="description", type="string", nullable=true, example="A high-quality widget for all your needs"),
 *         @OA\Property(property="price", type="number", format="float", example=99.99),
 *         @OA\Property(property="in_stock", type="boolean", example=true),
 *         @OA\Property(property="images", type="array", @OA\Items(ref="#/components/schemas/Image")),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 *     ),
 *     @OA\Schema(
 *         schema="Image",
 *         title="Product Image",
 *         required={"id", "url"},
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="url", type="string", format="uri", example="https://example.com/images/1.jpg"),
 *         @OA\Property(property="alt_text", type="string", nullable=true, example="Product image"),
 *         @OA\Property(property="position", type="integer", example=1),
 *         @OA\Property(property="width", type="integer", example=800),
 *         @OA\Property(property="height", type="integer", example=600)
 *     ),
 *     @OA\Schema(
 *         schema="Variant",
 *         title="Product Variant",
 *         required={"id", "product_id", "sku"},
 *         @OA\Property(property="id", type="string", format="uuid", example="660e8400-e29b-41d4-a716-446655440000"),
 *         @OA\Property(property="product_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *         @OA\Property(property="sku", type="string", example="SKU12345"),
 *         @OA\Property(property="price", type="number", format="float", example=99.99),
 *         @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=129.99),
 *         @OA\Property(property="option1", type="string", nullable=true, example="Red"),
 *         @OA\Property(property="option2", type="string", nullable=true, example="Large"),
 *         @OA\Property(property="inventory_quantity", type="integer", example=10),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 *     )
 * )
 */