<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ApiController;
use App\Services\ProductService;
use App\Services\ImageService;
use App\Services\SimilarityService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\StreamFactory;

class ApiControllerTest extends TestCase
{
    private $productService;
    private $imageService;
    private $similarityService;
    private $container;
    private $controller;

    protected function setUp(): void
    {
        $this->productService = $this->createMock(ProductService::class);
        $this->imageService = $this->createMock(ImageService::class);
        $this->similarityService = $this->createMock(SimilarityService::class);
        $this->container = $this->createMock(ContainerInterface::class);

        $this->controller = new ApiController(
            $this->productService,
            $this->imageService,
            $this->similarityService,
            $this->container
        );
    }

    public function testGetProducts()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $stream = (new StreamFactory())->createStream();

        $request->method('getQueryParams')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturn($response);

        $this->productService->method('getProducts')->willReturn([]);
        $this->productService->method('getTotalProducts')->willReturn(0);

        $this->controller->getProducts($request, $response, []);

        $this->assertEquals(
            json_encode(['products' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 20, 'total_pages' => 0]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            (string)$stream
        );
    }

    public function testSearchProducts()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $stream = (new StreamFactory())->createStream();

        $request->method('getQueryParams')->willReturn(['q' => 'test']);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturn($response);

        $this->productService->method('searchProducts')->willReturn([]);

        $this->controller->searchProducts($request, $response);

        $this->assertEquals(
            json_encode(['products' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            (string)$stream
        );
    }

    public function testGetProductOrHandleFound()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $stream = (new StreamFactory())->createStream();

        $product = new \App\Models\Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $request->method('getQueryParams')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturn($response);

        $this->productService->method('getProductOrHandle')->willReturn($product);
        $this->imageService->method('getProductImages')->willReturn([]);

        $this->controller->getProductOrHandle($request, $response, ['key' => 'test']);

        $this->assertJsonStringEqualsJsonString(
            json_encode(['product' => $product], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            (string)$stream
        );
    }

    public function testGetProductOrHandleNotFound()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $stream = (new StreamFactory())->createStream();

        $request->method('getQueryParams')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturn($response);
        $response->method('withStatus')->willReturn($response);

        $this->productService->method('getProductOrHandle')->willReturn(null);

        $this->controller->getProductOrHandle($request, $response, ['key' => 'test']);

        $this->assertEquals(
            json_encode(['error' => 'Product not found']),
            (string)$stream
        );
    }

    public function testGetCollectionProducts()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $stream = (new StreamFactory())->createStream();

        $request->method('getQueryParams')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturn($response);

        $this->productService->method('getCollectionProducts')->willReturn([]);
        $this->productService->method('getTotalCollectionProducts')->willReturn(0);

        $this->controller->getCollectionProducts($request, $response, ['handle' => 'all']);

        $this->assertEquals(
            json_encode(['products' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 50, 'total_pages' => 0]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            (string)$stream
        );
    }

    public function testGetRelated()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $stream = (new StreamFactory())->createStream();

        $product = new \App\Models\Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $request->method('getQueryParams')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturn($response);

        $this->productService->method('getProductOrHandle')->willReturn($product);
        $this->similarityService->method('getPrecomputedRelated')->willReturn([]);
        $this->productService->method('getRelatedProducts')->willReturn([]);

        $this->controller->getRelated($request, $response, ['key' => 'test']);

        $this->assertEquals(
            json_encode(['products' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            (string)$stream
        );
    }
}
