<?php
namespace App\Controllers;

use OpenApi\Annotations as OA;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\MsgPackResponse;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\ProductService;
use PDO;

/**
 * @OA\Info(
 *   version="1.0.0",
 *   title="Cosmos Products API",
 *   description="Read-only products API with MessagePack support",
 *   @OA\Contact(email="support@example.com"),
 *   @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 * )
 * @OA\Server(url="/cosmos", description="API Server (context path)")
 * @OA\SecurityScheme(securityScheme="api_key", type="apiKey", in="header", name="X-API-KEY")
 * @OA\Tag(name="Products", description="API Endpoints for Products")
 */
class ApiController
{
    private ProductService $productService;
    private ImageService $imageService;
    private \App\Services\SimilarityService $similarityService;

    public function __construct(ProductService $productService, ImageService $imageService, \App\Services\SimilarityService $similarityService)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
        $this->similarityService = $similarityService;
    }

    private function outputResponse(Response $response, array $data, string $format = 'json'): Response
    {
        if ($format === 'msgpack') {
            return MsgPackResponse::withMsgPack($response, $data);
        } else {
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * @OA\Get(
     *     path="/products",
     *     summary="Get a list of products",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="include_variants",
     *         in="query",
     *         description="Set to 1 to include parsed variants in the response",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0,1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format: json (default) or msgpack",
     *         required=false,
     *         @OA\Schema(type="string", enum={"json","msgpack"}, default="json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Product")
     *         )
     *     ),
     *     security={{ "api_key": {} }}
     * )
     */
    public function getProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $format = $params['format'] ?? 'json';

        $products = $this->productService->getProducts($page, $limit);
        $total = $this->productService->getTotalProducts();

        // mark in_stock true and optionally attach variants
        $includeVariants = (isset($params['include_variants']) && (int)$params['include_variants'] === 1);
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
     *     summary="Search products via FTS",
     *     tags={"Products"},
     *     @OA\Parameter(name="q", in="query", description="Search query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="fields", in="query", description="Comma-separated allowed fields to return", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="include_variants", in="query", description="Set to 1 to include parsed variants", required=false, @OA\Schema(type="integer", enum={0,1}, default=0)),
     *     @OA\Parameter(name="format", in="query", description="json or msgpack", required=false, @OA\Schema(type="string", enum={"json","msgpack"}, default="json")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product"))
     *         )
     *     ),
     *     security={{ "api_key": {} }}
     * )
     */
    // --- 2. Search Products (FTS) ---
    public function searchProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';

        $selectFields = '*';
        if (!empty($fieldsParam)) {
            $requestedFields = array_map('trim', explode(',', $fieldsParam));
            $validFields = array_intersect($requestedFields, Product::ALLOWED_PRODUCT_FIELDS);

            if (!empty($validFields)) {
                if (!in_array('id', $validFields)) {
                    $validFields[] = 'id';
                }
                $selectFields = implode(', ', $validFields);
            }
        }

        $products = $this->productService->searchProducts($query, $selectFields);

        $includeVariants = (isset($params['include_variants']) && (int)$params['include_variants'] === 1);
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
     *     tags={"Products"},
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json","msgpack"}, default="json")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(type="object", @OA\Property(property="product", ref="#/components/schemas/Product"))),
     *     @OA\Response(response=404, description="Not Found"),
     *     security={{ "api_key": {} }}
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
     *     tags={"Collections"},
     *     @OA\Parameter(name="handle", in="path", required=true, @OA\Schema(type="string", enum={"all","featured","sale","new","bestsellers","trending"})),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=50, maximum=100)),
     *     @OA\Parameter(name="fields", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="include_variants", in="query", required=false, @OA\Schema(type="integer", enum={0,1}, default=0)),
     *     @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json","msgpack"}, default="json")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(type="object",
     *         @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *         @OA\Property(property="meta", type="object")
     *     )),
     *     security={{ "api_key": {} }}
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

        $products = $this->productService->getCollectionProducts($collectionHandle, $page, $limit, $fieldsParam);

        $includeVariants = (isset($params['include_variants']) && (int)$params['include_variants'] === 1);
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
     *     summary="Get related products for a given product",
     *     tags={"Products"},
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=8, maximum=12)),
     *     @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json","msgpack"}, default="json")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(type="object",
     *         @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product"))
     *     )),
     *     @OA\Response(response=404, description="Not Found"),
     *     security={{ "api_key": {} }}
     * )
     */
    public function getRelated(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $format = $request->getQueryParams()['format'] ?? 'json';
        $limit = min(12, max(1, (int)($request->getQueryParams()['limit'] ?? 8)));

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
