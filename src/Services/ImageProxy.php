<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use OpenApi\Annotations as OA;

class ImageProxy
{
    private string $cacheDir;
    private int $cacheHours = 24;
    private int $maxFileSize = 10 * 1024 * 1024;
    private Client $httpClient;
    private array $allowedDomains = [
        'cdn.shopify.com',
        'shopify.com',
        'cdn.moritotabi.com'
    ];

    public function __construct(array $config)
    {
        $this->cacheDir = $config['image_proxy']['cache_dir'];
        $this->httpClient = new Client();
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * @OA\Get(
     *   path="/image-proxy",
     *   summary="Proxy and cache an external image",
     *   tags={"Images"},
     *   @OA\Parameter(name="url", in="query", required=true, description="Source image URL (cdn.shopify.com, shopify.com, cdn.moritotabi.com)", @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Image content"),
     *   @OA\Response(response=400, description="Bad request or invalid domain"),
     *   @OA\Response(response=404, description="Image not found")
     * )
     */
    public function output(\Psr\Http\Message\ServerRequestInterface $request): void
    {
        $url = $request->getQueryParams()['url'] ?? null;
        if (!$url) {
            http_response_code(400);
            echo "Missing url";
            return;
        }

        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $this->allowedDomains)) {
            http_response_code(400);
            echo "Invalid image domain.";
            return;
        }

        $hash = md5($url);
        $cacheFile = $this->cacheDir . '/' . $hash;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < ($this->cacheHours * 3600)) {
            $this->serveCachedImage($cacheFile);
            return;
        }

        $this->fetchAndCacheImage($url, $cacheFile);
    }

    private function serveCachedImage(string $cacheFile): void
    {
        $mime = mime_content_type($cacheFile);
        header("Content-Type: $mime");
        readfile($cacheFile);
    }

    private function fetchAndCacheImage(string $url, string $cacheFile): void
    {
        try {
            $response = $this->httpClient->get($url, [
                'sink' => $cacheFile,
                'timeout' => 10,
                'on_headers' => function (\GuzzleHttp\Psr7\Response $response) {
                    $contentType = $response->getHeaderLine('Content-Type');
                    $contentLength = $response->getHeaderLine('Content-Length');

                    if ($contentLength > $this->maxFileSize) {
                        throw new \Exception("File too large.");
                    }
                }
            ]);
            $this->serveCachedImage($cacheFile);
        } catch (RequestException $e) {
            http_response_code(404);
            echo "Image not found or could not be fetched: " . $e->getMessage();
        } catch (\Exception $e) {
            @unlink($cacheFile);
            http_response_code(400);
            echo "Error fetching image: " . $e->getMessage();
        }
    }
}
