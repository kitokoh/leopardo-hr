<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Contracts;

interface AccessTokenServiceInterface
{
    public function issue(int $cameraId, int $employeeId, int $ttlSeconds = 300): string;

    public function verify(string $token): ?array;

    public function revoke(string $token): void;
}
