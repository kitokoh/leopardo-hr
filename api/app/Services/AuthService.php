<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
{
    /**
     * @return array{employee: Employee, token: string, token_type: string, token_expires_at: ?string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $lookup = null;
        if (Schema::hasTable('user_lookups')) {
            $lookup = DB::table($this->lookupTable())
                ->where('email', $email)
                ->first();
        }

        /** @var Employee|null $employee */
        $employee = null;

        if ($lookup) {
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $lookup->company_id)
                ->where('id', $lookup->employee_id)
                ->first();
        }

        if (! $employee) {
            $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();
            $employee?->syncUserLookup();
        }

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

        $employee->forceFill(['last_login_at' => now()])->saveQuietly();

        $tokenName = $deviceName ?: 'api';
        $expirationMinutes = (int) config('sanctum.expiration', 0);
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
        $tokenResult = $employee->createToken($tokenName, ['*'], $expiresAt);

        return [
            'employee' => $employee,
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'token_expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function lookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }
}
