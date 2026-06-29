<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;

/**
 * Use Case : Authentification d'un employé.
 *
 * Orchestrates the login flow by delegating technical concerns
 * to the AuthService (Infrastructure layer), keeping the
 * controller thin.
 *
 * @return array{employee: Employee, token: string, token_type: string, token_expires_at: ?string}
 */
final class LoginAction
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * @return array{employee: Employee, token: string, token_type: string, token_expires_at: ?string}
     */
    public function execute(string $email, string $password, ?string $deviceName = null): array
    {
        return $this->authService->login(
            email: $email,
            password: $password,
            deviceName: $deviceName,
        );
    }
}
