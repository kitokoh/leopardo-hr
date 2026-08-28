<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

final class FuelReadingException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $code,
        private readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->code;
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function render(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        return new \Illuminate\Http\JsonResponse(['error' => $this->code], $this->status);
    }
}
