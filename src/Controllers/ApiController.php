<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\MsgPackResponse;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\ProductService;
use PDO;

class ApiController
{
    private ProductService $productService;
    private ImageService $imageService;

    public function __construct(ProductService $productService, ImageService $imageService)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
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

    public function getProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(8, max(1, (int) ($params['limit'] ?? 50)));
        $format = $params['format'] ?? 'json';

        $products = $this->productService->getProducts($page, $limit);
        $total = $this->productService->getTotalProducts();

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

        $product->images = $this->imageService->getProductImages($product->id);

        $data = ['product' => $product];
        return $this->outputResponse($response, $data, $format);
    }

    public function getCollectionProducts(Request $request, Response $response, array $args): Response
    {
        $collectionHandle = strtolower($args['handle']);
        $params = $request->getQueryParams();
        $fieldsParam = $params['fields'] ?? '';
        $format = $params['format'] ?? 'json';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));

        $products = $this->productService->getCollectionProducts($collectionHandle, $page, $limit, $fieldsParam);

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
}
