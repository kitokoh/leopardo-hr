<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;

/**
 * Use Case : Inscription d'un nouvel employé (rôle "ordinary").
 *
 * Creates the employee record and issues an API token.
 *
 * Audit expert 2026-08-15 (issue #2617) : l'inscription publique est le point
 * d'entrée du parcours self-onboarding « compte ordinary → company request »
 * (le compte n'a volontairement pas de company_id tant que la demande
 * d'entreprise n'est pas approuvée — le TenantMiddleware laisse passer les
 * rôles ordinary sans tenant sur les routes pré-tenant, et le login est
 * désormais possible pour ces comptes, cf. AuthService::login).
 *
 * @return array{employee: Employee, token: string, token_type: string}
 */
final class RegisterAction
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, password: string, device_name?: string}  $data
     * @return array{employee: Employee, token: string, token_type: string}
     */
    public function execute(array $data): array
    {
        /** @var Employee $employee */
        $employee = Employee::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        $tokenName = $data['device_name'] ?? 'api';
        $token = $employee->createToken($tokenName);

        return [
            'employee' => $employee,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
