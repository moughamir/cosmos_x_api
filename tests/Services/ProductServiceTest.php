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
}
