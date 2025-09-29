<?php

namespace App\Services;

use PDO;
use Redis;
use Psr\Log\LoggerInterface;

class HealthCheckService
{
    private PDO $db;
    private Redis $redis;
    private LoggerInterface $logger;
    private array $config;

    public function __construct(
        PDO $db,
        Redis $redis,
        LoggerInterface $logger,
        array $config
    ) {
        $this->db = $db;
        $this->redis = $redis;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function check(): array
    {
        $status = [
            'status' => 'healthy',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'services' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'storage' => $this->checkStorage(),
                'memory' => $this->checkMemory(),
            ]
        ];

        // If any service is down, mark overall status as unhealthy
        foreach ($status['services'] as $service) {
            if ($service['status'] !== 'healthy') {
                $status['status'] = 'unhealthy';
                break;
            }
        }

        return $status;
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $this->db->query('SELECT 1')->fetch();
            $latency = microtime(true) - $start;

            return [
                'status' => 'healthy',
                'latency' => round($latency * 1000, 2) . 'ms',
                'type' => 'sqlite',
                'version' => $this->db->getAttribute(PDO::ATTR_SERVER_VERSION),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Database health check failed: ' . $e->getMessage());
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            $pong = $this->redis->ping();
            $latency = microtime(true) - $start;

            return [
                'status' => $pong ? 'healthy' : 'unhealthy',
                'latency' => round($latency * 1000, 2) . 'ms',
                'version' => $this->redis->info()['redis_version'] ?? 'unknown',
                'used_memory' => $this->formatBytes($this->redis->info()['used_memory'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Redis health check failed: ' . $e->getMessage());
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        $paths = [
            'logs' => __DIR__ . '/../../logs',
            'cache' => __DIR__ . '/../../var/cache',
            'uploads' => __DIR__ . '/../../public/uploads',
        ];

        $results = [];
        $status = 'healthy';

        foreach ($paths as $name => $path) {
            try {
                $isWritable = is_writable($path);
                $freeSpace = disk_free_space(dirname($path));
                $totalSpace = disk_total_space(dirname($path));

                $results[$name] = [
                    'writable' => $isWritable,
                    'free_space' => $this->formatBytes($freeSpace),
                    'total_space' => $this->formatBytes($totalSpace),
                    'used_percent' => round((1 - ($freeSpace / $totalSpace)) * 100, 2) . '%',
                ];

                if (!$isWritable) {
                    $status = 'degraded';
                }
            } catch (\Throwable $e) {
                $this->logger->error("Storage check failed for {$path}: " . $e->getMessage());
                $results[$name] = ['error' => $e->getMessage()];
                $status = 'unhealthy';
            }
        }

        return [
            'status' => $status,
            'details' => $results,
        ];
    }

    private function checkMemory(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);

        return [
            'status' => 'healthy',
            'memory_limit' => $this->formatBytes($memoryLimitBytes),
            'memory_used' => $this->formatBytes($memoryUsage),
            'memory_peak' => $this->formatBytes($peakUsage),
            'usage_percent' => round(($memoryUsage / $memoryLimitBytes) * 100, 2) . '%',
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            // no break
            case 'm':
                $memoryLimit *= 1024;
            // no break
            case 'k':
                $memoryLimit *= 1024;
        }

        return $memoryLimit;
    }
}
