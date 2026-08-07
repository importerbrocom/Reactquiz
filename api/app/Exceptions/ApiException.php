<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Base for every deliberately-thrown API failure.
 *
 * Carries a machine-readable code so the frontend can branch on behaviour
 * (e.g. show the "locked" screen) without string-matching a human message.
 */
abstract class ApiException extends Exception
{
    protected int $status = 400;

    protected string $errorCode = 'REQUEST_FAILED';

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct(string $message = '', array $meta = [], ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $this->defaultMessage(), 0, $previous);
        $this->meta = $meta;
    }

    abstract protected function defaultMessage(): string;

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            status: $this->status,
            code: $this->errorCode,
            meta: $this->meta !== [] ? $this->meta : null,
        );
    }
}
