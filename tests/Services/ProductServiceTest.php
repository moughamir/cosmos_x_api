<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\ProductService;
use PDO;
use PDOStatement;

class ProductServiceTest extends TestCase
{
    private $pdo;
    private $stmt;
    private $service;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
        $this->service = new ProductService($this->pdo);
    }

    public function testGetProducts()
    {
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->service->getProducts(1, 10);

        $this->assertIsArray($result);
    }

    public function testGetTotalProducts()
    {
        $this->pdo->method('query')->willReturn($this->stmt);
        $this->stmt->method('fetchColumn')->willReturn(10);

        $result = $this->service->getTotalProducts();

        $this->assertEquals(10, $result);
    }

    public function testSearchProducts()
    {
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->service->searchProducts('test');

        $this->assertIsArray($result);
    }

    public function testGetProductOrHandleWithId()
    {
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(new \App\Models\Product());

        $result = $this->service->getProductOrHandle('1');

        $this->assertInstanceOf(\App\Models\Product::class, $result);
    }

    public function testGetProductOrHandleWithHandle()
    {
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(new \App\Models\Product());

        $result = $this->service->getProductOrHandle('test-handle');

        $this->assertInstanceOf(\App\Models\Product::class, $result);
    }

    public function testGetCollectionProducts()
    {
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->service->getCollectionProducts('all', 1, 10, '*');

        $this->assertIsArray($result);
    }

    public function testGetTotalCollectionProducts()
    {
        $this->pdo->method('query')->willReturn($this->stmt);
        $this->stmt->method('fetchColumn')->willReturn(5);

        $result = $this->service->getTotalCollectionProducts('all');

        $this->assertEquals(5, $result);
    }

    public function testAttachVariantsToProduct()
    {
        $product = new \App\Models\Product();
        $product->raw_json = json_encode(['variants' => [['id' => 1]]]);

        $this->service->attachVariantsToProduct($product);

        $this->assertIsArray($product->variants);
        $this->assertCount(1, $product->variants);
    }
}
