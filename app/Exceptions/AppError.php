<?php

namespace App\Exceptions;

use Exception;

class AppError extends Exception
{
    protected int $statusCode;
    protected string $errorType;

    public function __construct(string $message, int $statusCode = 400, string $errorType = 'error')
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public static function validation(string $message): self
    {
        return new self($message, 422, 'validation_error');
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self($message, 404, 'not_found');
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409, 'conflict');
    }

    public static function authentication(string $message = 'Unauthenticated'): self
    {
        return new self($message, 401, 'authentication_error');
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self($message, 403, 'forbidden');
    }

    public static function server(string $message = 'Internal server error'): self
    {
        return new self($message, 500, 'server_error');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'type' => $this->errorType,
                'message' => $this->message,
            ],
        ], $this->statusCode);
    }
}
