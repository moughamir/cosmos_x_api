<?php

namespace App\Exception;

class HttpException extends \RuntimeException
{
    private array $errors;
    private int $statusCode;

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self($message, 404);
    }

    public static function badRequest(string $message = 'Bad request'): self
    {
        return new self($message, 400);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self($message, 403);
    }
}
