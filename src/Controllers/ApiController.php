<?php
namespace App\Controllers;

use OpenApi\Annotations as OA;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\MsgPackResponse;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\ProductService;
use App\Traits\ValidatesRequests;
use PDO;

/**
 * @OA\Tag(name="Products", description="Operations about products")
 * @OA\Tag(name="Search", description="Search operations")
 * @OA\Tag(name="Collections", description="Product collections and categories")
 * 
 * @OA\Response(
 *     response="UnauthorizedError",
 *     description="API key is missing or invalid",
 *     @OA\JsonContent(
 *         @OA\Property(property="error", type="object",
 *             @OA\Property(property="code", type="integer", example=401),
 *             @OA\Property(property="message", type="string", example="Unauthorized. Missing or invalid API Key.")
 *         )
 *     )
 * )
 * @OA\Response(
 *     response="NotFoundError",
 *     description="Resource not found",
 *     @OA\JsonContent(
 *         @OA\Property(property="error", type="object",
 *             @OA\Property(property="code", type="integer", example=404),
 *             @OA\Property(property="message", type="string", example="The requested resource was not found.")
 *         )
 *     )
 * )
 * @OA\Response(
 *     response="ValidationError",
 *     description="Invalid input",
 *     @OA\JsonContent(
 *         @OA\Property(property="error", type="object",
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="message", type="string", example="Invalid input data"),
 *             @OA\Property(property="details", type="object", additionalProperties={"type": "array", "items": {"type": "string"}})
 *         )
 *     )
 * )
 */
class ApiController
{
    use ValidatesRequests;
    
    private ProductService $productService;
    private ImageService $imageService;
    private \App\Services\SimilarityService $similarityService;

    public function __construct(ProductService $productService, ImageService $imageService, \App\Services\SimilarityService $similarityService)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
        $this->similarityService = $similarityService;
    }

    private function validateCollectionHandle(string $handle): bool
    {
        $validHandles = ['all', 'featured', 'sale', 'new', 'bestsellers', 'trending'];
        return in_array(strtolower($handle), $validHandles);
    }

    private function validateFormat(string $format): bool
    {
        return in_array($format, ['json', 'msgpack']);
    }

    private function sanitizeSearchQuery(string $query): string
    {
        // Remove potentially harmful characters and limit length
        $sanitized = trim(preg_replace('/[^\w\s\-\.]/u', ' ', $query));
        return mb_substr($sanitized, 0, 255);
    }

    private function validateFieldsParam(string $fieldsParam): array
    {
        if (empty($fieldsParam)) {
            return [];
        }

        $requestedFields = array_map('trim', explode(',', $fieldsParam));
        return array_intersect($requestedFields, Product::ALLOWED_PRODUCT_FIELDS);
    }

    /**
     * Outputs the response in the requested format (JSON or MessagePack)
     *
     * @param Response $response The PSR-7 response object
     * @param mixed $data The data to encode
     * @param string $format The output format ('json' or 'msgpack')
     * @return Response
     * @throws \RuntimeException If the requested format is not supported
     */
    protected function outputResponse(Response $response, $data, string $format = 'json'): Response
    {
        if ($format === 'msgpack') {
            if (extension_loaded('msgpack')) {
                $response->getBody()->write(msgpack_pack($data));
                return $response->withHeader('Content-Type', 'application/x-msgpack');
            } else {
                // Fallback to JSON if msgpack extension is not available
                $response->getBody()->write(json_encode([
                    'error' => [
                        'code' => 500,
                        'message' => 'MessagePack extension is not available',
                        'details' => 'The server does not support MessagePack format. Falling back to JSON.'
                    ]
                ], JSON_PRETTY_PRINT));
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
        }

        // Default to JSON
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response;
    }

    /**
     * @OA\Get(
     *     path="/products",
     *     summary="List all products",
     *     description="Retrieves a paginated list of products with optional filtering and sorting",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     security={{"api_key": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Parameter(
     *         name="include_variants",
     *         in="query",
     *         description="Set to 1 to include product variants in the response",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort order (prefix with - for descending)",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"created_at", "updated_at", "name", "price"},
     *             default="created_at"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             default="desc"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"json", "msgpack"},
     *             default="json"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="meta", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedError"),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too Many Requests",
     *         @OA\Header(
     *             header="Retry-After",
     *             description="Number of seconds to wait before retrying",
     *             @OA\Schema(type="integer")
     *         ),
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function getProducts(Request $request, Response $response, array $args): Response
    {
        // Get query parameters with defaults
        $queryParams = $request->getQueryParams();
        
        // Set default values if not provided
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $limit = min(50, max(1, (int)($queryParams['limit'] ?? 20)));
        $sort = in_array($queryParams['sort'] ?? 'created_at', ['created_at', 'updated_at', 'name', 'price']) 
            ? $queryParams['sort'] 
            : 'created_at';
        $order = strtoupper($queryParams['order'] ?? 'desc') === 'ASC' ? 'ASC' : 'DESC';
        $format = strtolower($queryParams['format'] ?? 'json');
        
        // Validate format
        if (!in_array($format, ['json', 'msgpack'])) {
            $format = 'json';
        }

        // Validate format
        if (!$this->validateFormat($format)) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode(['error' => 'Invalid format. Must be json or msgpack']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $products = $this->productService->getProducts($page, $limit);
        $total = $this->productService->getTotalProducts();

        // mark in_stock true and optionally attach variants
        $includeVariants = (isset($params['include_variants']) && (int) $params['include_variants'] === 1);
        foreach ($products as $p) {
            $p->in_stock = true;
            if ($includeVariants) {
                $this->productService->attachVariantsToProduct($p);
            }
        }

        if (empty($products)) {
            return $this->outputResponse($response, ['products' => []], $format);
        }

        $productIds = array_map(fn($p) => $p->id, $products);
        $allImages = $this->imageService->getImagesForProducts($productIds);

        $imagesByProductId = [];
        foreach ($allImages as $img) {
            $imagesByProductId[$img->product_id][] = $img;
        }
        foreach ($products as $product) {
            $product->images = $imagesByProductId[$product->id] ?? [];
        }

        $data = [
            'products' => $products,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     *     path="/products/search",
     *     summary="Search products",
     *     description="Search for products using full-text search with support for filtering and field selection",
     *     operationId="searchProducts",
     *     tags={"Search", "Products"},
     *     security={{"api_key": {}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query (supports full-text search syntax)",
     *         required=true,
     *         example="premium widget",
     *         @OA\Schema(type="string", minLength=2)
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         description="Comma-separated list of fields to return in the response (e.g., 'id,name,price')",
     *         required=false,
     *         example="id,name,price,images",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include_variants",
     *         in="query",
     *         description="Set to 1 to include product variants in the response",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="meta", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedError"),
     *     @OA\Response(
     *         response=404,
     *         description="No products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function searchProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = $this->sanitizeSearchQuery($params['q'] ?? '');
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';

        // Validate format
        if (!$this->validateFormat($format)) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode(['error' => 'Invalid format. Must be json or msgpack']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $validFields = $this->validateFieldsParam($fieldsParam);
        $selectFields = !empty($validFields) ? implode(', ', array_unique([...$validFields, 'id'])) : '*';

        $products = $this->productService->searchProducts($query, $selectFields);

        $includeVariants = (isset($params['include_variants']) && (int) $params['include_variants'] === 1);
        foreach ($products as $p) {
            $p->in_stock = true;
            if ($includeVariants) {
                $this->productService->attachVariantsToProduct($p);
            }
        }

        if (!empty($products)) {
            $productIds = array_map(fn($p) => $p->id, $products);
            $allImages = $this->imageService->getImagesForProducts($productIds);

            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img->product_id][] = $img;
            }

            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }

        $data = ['products' => $products];
        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     *     path="/products/{key}",
     *     summary="Get product by ID or handle",
     *     description="Retrieves a single product by its unique identifier or handle. The endpoint automatically detects whether the provided key is a UUID (ID) or a handle and returns the corresponding product.",
     *     operationId="getProductByIdOrHandle",
     *     tags={"Products"},
     *     security={{"api_key": {}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         description="Product ID (UUID) or handle (URL-friendly name)",
     *         required=true,
     *         example="550e8400-e29b-41d4-a716-446655440000"
     *     ),
     *     @OA\Parameter(
     *         name="include_variants",
     *         in="query",
     *         description="Set to 1 to include product variants in the response",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         description="Comma-separated list of fields to include in the response (e.g., 'id,name,price,images')",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="product", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/responses/UnauthorizedError")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="integer", example=404),
     *                 @OA\Property(property="message", type="string", example="Product not found")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too Many Requests",
     *         @OA\Header(
     *             header="Retry-After",
     *             description="Number of seconds to wait before retrying",
     *             @OA\Schema(type="integer")
     *         ),
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function getProductOrHandle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $format = $request->getQueryParams()['format'] ?? 'json';

        $product = $this->productService->getProductOrHandle($key);

        if (!$product) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $product->in_stock = true;
        $this->productService->attachVariantsToProduct($product);
        $product->images = $this->imageService->getProductImages($product->id);

        $data = ['product' => $product];
        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     *     path="/collections/{handle}",
     *     summary="Get products from a collection",
     *     description="Retrieves a paginated list of products from the specified collection. Supports various collection types including featured, sale, new arrivals, bestsellers, and trending products.",
     *     operationId="getCollectionProducts",
     *     tags={"Collections"},
     *     security={{"api_key": {}}},
     *     @OA\Parameter(
     *         name="handle",
     *         in="path",
     *         description="Collection identifier",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"all", "featured", "sale", "new", "bestsellers", "trending"},
     *             example="featured"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         description="Comma-separated list of fields to include in the response (e.g., 'id,name,price,images')",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include_variants",
     *         in="query",
     *         description="Set to 1 to include product variants in the response",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Collection products retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 ref="#/components/schemas/Pagination"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/responses/UnauthorizedError")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Collection not found or no products in collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function getCollectionProducts(Request $request, Response $response, array $args): Response
    {
        $collectionHandle = strtolower($args['handle']);
        $params = $request->getQueryParams();
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));

        // Validate collection handle
        if (!$this->validateCollectionHandle($collectionHandle)) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode(['error' => 'Invalid collection handle']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Validate format
        if (!$this->validateFormat($format)) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode(['error' => 'Invalid format. Must be json or msgpack']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $validFields = $this->validateFieldsParam($fieldsParam);
        $selectFields = !empty($validFields) ? implode(', ', array_unique([...$validFields, 'id'])) : '*';

        $products = $this->productService->getCollectionProducts($collectionHandle, $page, $limit, $selectFields);

        $includeVariants = (isset($params['include_variants']) && (int) $params['include_variants'] === 1);
        foreach ($products as $p) {
            $p->in_stock = true;
            if ($includeVariants) {
                $this->productService->attachVariantsToProduct($p);
            }
        }

        if (!empty($products)) {
            $productIds = array_map(fn($p) => $p->id, $products);
            $allImages = $this->imageService->getImagesForProducts($productIds);

            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img->product_id][] = $img;
            }

            foreach ($products as $product) {
                $product->images = $imagesByProductId[$product->id] ?? [];
            }
        }
        $data = ['products' => $products];
        if ($collectionHandle !== 'featured') {
            $total = $this->productService->getTotalCollectionProducts($collectionHandle);
            $data['meta'] = [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];
        }

        return $this->outputResponse($response, $data, $format);
    }
    /**
     * @OA\Get(
     *     path="/products/{key}/related",
     *     summary="Get related products",
     *     description="Retrieves a list of products that are related to the specified product. Related products are determined based on product categories, tags, and other similarity metrics. The system first checks for precomputed relationships and falls back to on-the-fly similarity calculation if needed.",
     *     operationId="getRelatedProducts",
     *     tags={"Products"},
     *     security={{"api_key": {}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         description="Product ID (UUID) or handle (URL-friendly name)",
     *         required=true,
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of related products to return (1-12)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1,
     *             maximum=12,
     *             default=8
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="include_variants",
     *         in="query",
     *         description="Set to 1 to include product variants in the response",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         description="Comma-separated list of fields to include in the response (e.g., 'id,name,price,images')",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Related products retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 description="Array of related products",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/responses/UnauthorizedError")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="integer", example=404),
     *                 @OA\Property(property="message", type="string", example="Product not found")
     *             )
     *         )
     *     )
     * )
     */
    public function getRelated(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $format = $request->getQueryParams()['format'] ?? 'json';
        $limit = min(12, max(1, (int) ($request->getQueryParams()['limit'] ?? 8)));

        $product = $this->productService->getProductOrHandle($key);
        if (!$product) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Prefer precomputed similarities; fall back to on-the-fly heuristic
        $related = $this->similarityService->getPrecomputedRelated($product->id, $limit);
        if (empty($related)) {
            $related = $this->productService->getRelatedProducts($product->id, $limit);
        }
        if (!empty($related)) {
            $productIds = array_map(fn($p) => $p->id, $related);
            $allImages = $this->imageService->getImagesForProducts($productIds);
            $imagesByProductId = [];
            foreach ($allImages as $img) {
                $imagesByProductId[$img->product_id][] = $img;
            }
            foreach ($related as $p) {
                $p->in_stock = true;
                $p->images = $imagesByProductId[$p->id] ?? [];
            }
        }
        $data = ['products' => $related];
        return $this->outputResponse($response, $data, $format);
    }
}
