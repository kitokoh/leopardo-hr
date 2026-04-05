<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @return array{employee: Employee, token: string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();

        if (! $employee || ! Hash::check($password, $employee->password_hash)) {
            abort(401, 'INVALID_CREDENTIALS');
        }

        $company = $employee->company;
        if (! $company) {
            abort(403, 'COMPANY_NOT_FOUND');
        }

        if (in_array($company->status, ['suspended', 'expired'], true)) {
            abort(403, 'ACCOUNT_SUSPENDED');
        }

        if ($employee->status !== 'active') {
            abort(403, 'EMPLOYEE_NOT_ACTIVE');
        }

        $tokenName = $deviceName ?: 'api';
        $token = $employee->createToken($tokenName)->plainTextToken;

        return [
            'employee' => $employee,
            'token' => $token,
        ];
    }
}

