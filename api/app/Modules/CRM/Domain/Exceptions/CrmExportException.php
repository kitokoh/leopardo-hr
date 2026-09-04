<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

abstract class CrmExportException extends RuntimeException
{
    abstract public function errorCode(): string;

    public function httpStatus(): int
    {
        return 422;
    }

    public function render(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        return new \Illuminate\Http\JsonResponse(['error' => $this->errorCode()], $this->httpStatus());
    }
}
