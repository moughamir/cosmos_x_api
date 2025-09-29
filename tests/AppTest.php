<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class AppTest extends TestCase
{
    private Client $http;

    protected function setUp(): void
    {
        $this->http = new Client([
            'base_uri' => 'http://localhost',
            'http_errors' => false,
        ]);
    }

    public function test_health_check_returns_healthy_status(): void
    {
        $response = $this->http->get('/cosmos/health');

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('status', $body);
        $this->assertEquals('healthy', $body['status']);
        $this->assertArrayHasKey('services', $body);
        $this->assertArrayHasKey('database', $body['services']);
        $this->assertArrayHasKey('redis', $body['services']);
        $this->assertArrayHasKey('storage', $body['services']);
        $this->assertArrayHasKey('memory', $body['services']);
    }
}