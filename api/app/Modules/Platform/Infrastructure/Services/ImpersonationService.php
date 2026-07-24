<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Exceptions\AccountSuspendedException;
use App\Modules\Platform\Domain\Models\PlatformImpersonationSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * PA2-ADM-006 — Secure super-admin impersonation ("log in as this employee").
 *
 * Every session is: super-admin only, requires a mandatory reason, is
 * recorded for audit (who/whom/why/when), and is hard time-limited (a
 * fresh Sanctum token scoped to a short TTL, distinguishable from the
 * employee's normal tokens via the `impersonation:<session_id>` ability so
 * it can be told apart and revoked independently). Ending a session — or
 * letting it expire — revokes exactly that token.
 */
class ImpersonationService
{
    /**
     * Default and maximum impersonation session lifetime, in minutes.
     * Kept short: this is a support/debugging tool, not a login bypass.
     */
    private const int DEFAULT_TTL_MINUTES = 30;

    private const int MAX_TTL_MINUTES = 120;

    /**
     * @return array{session: PlatformImpersonationSession, employee: Employee, token: string, expires_at: Carbon}
     */
    public function start(
        SuperAdmin $superAdmin,
        string $companyId,
        int $employeeId,
        string $reason,
        ?string $ipAddress = null,
        ?int $ttlMinutes = null,
    ): array {
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            throw (new ModelNotFoundException)->setModel(Company::class, [$companyId]);
        }

        if (in_array($company->status, ['suspended', 'expired'], true)) {
            throw new AccountSuspendedException;
        }

        $ttl = min(max($ttlMinutes ?? self::DEFAULT_TTL_MINUTES, 1), self::MAX_TTL_MINUTES);
        $expiresAt = now()->addMinutes($ttl);

        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);

        return $tenantManager->withinTenant($company, function () use ($superAdmin, $company, $employeeId, $reason, $ipAddress, $expiresAt): array {
            /** @var Employee|null $employee */
            $employee = Employee::query()->find($employeeId);

            if (! $employee instanceof Employee) {
                throw (new ModelNotFoundException)->setModel(Employee::class, [$employeeId]);
            }

            if (in_array($employee->status, ['archived', 'suspended'], true)) {
                throw new AccountSuspendedException;
            }

            $tokenResult = $employee->createToken(
                name: 'impersonation',
                abilities: ['*', 'impersonation:pending'],
                expiresAt: $expiresAt,
            );

            $session = PlatformImpersonationSession::create([
                'super_admin_id' => $superAdmin->id,
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'personal_access_token_id' => $tokenResult->accessToken->id,
                'company_name' => $company->name,
                'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                'employee_email' => $employee->email,
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'expires_at' => $expiresAt,
            ]);

            // The ability needs the session id, which only exists once the
            // session row is created; the token itself was minted first so
            // personal_access_token_id could be recorded on the session.
            $tokenResult->accessToken->forceFill([
                'abilities' => ['*', 'impersonation:'.$session->id],
            ])->save();

            Log::channel('structured')->warning('platform.impersonation.started', [
                'super_admin_id' => $superAdmin->id,
                'super_admin_email' => $superAdmin->email,
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'session_id' => $session->id,
                'reason' => $reason,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return [
                'session' => $session,
                'employee' => $employee,
                'token' => $tokenResult->plainTextToken,
                'expires_at' => $expiresAt,
            ];
        });
    }

    public function end(PlatformImpersonationSession $session, ?SuperAdmin $endedBy = null): void
    {
        if ($session->ended_at !== null) {
            return;
        }

        $session->forceFill([
            'ended_at' => now(),
            'ended_by' => $endedBy?->id,
        ])->save();

        if ($session->personal_access_token_id !== null) {
            $this->revokeToken($session->personal_access_token_id);
        }

        Log::channel('structured')->warning('platform.impersonation.ended', [
            'session_id' => $session->id,
            'super_admin_id' => $session->super_admin_id,
            'company_id' => $session->company_id,
            'employee_id' => $session->employee_id,
            'ended_by' => $endedBy?->id,
        ]);
    }

    private function revokeToken(int $tokenId): void
    {
        // personal_access_tokens is a public-schema table (see
        // database/migrations/public/2026_04_04_000010_create_personal_access_tokens_table.php)
        // and every tenant search_path falls back to `public`, so this
        // resolves correctly regardless of the currently active tenant
        // schema — no need to switch tenant context just to delete a token.
        $tokenClass = config('sanctum.personal_access_token_model', PersonalAccessToken::class);
        $tokenClass::query()->whereKey($tokenId)->delete();
    }
}
