<?php

namespace App\Renderer;

use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

class JsonErrorRenderer implements ErrorRendererInterface
{
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        file_put_contents('/tmp/error.log', 'displayErrorDetails: ' . ($displayErrorDetails ? 'true' : 'false') . "\n", FILE_APPEND);
        $error = [
            'error' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]
        ];

        if ($displayErrorDetails) {
            $error['error']['details'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return json_encode($error, JSON_PRETTY_PRINT);
    }
}
