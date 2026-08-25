<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * #5436 — 2FA/TOTP des comptes entreprise.
 *
 * - Enrôlement : secret + QR (TotpService), confirmation par code, codes de
 *   récupération hachés (8, usage unique).
 * - Connexion : challenge émis par `AuthController::login` quand le compte
 *   est enrôlé ; `verifyChallenge()` délivre le token Sanctum avec les
 *   abilities tenant (mêmes règles que `AuthService::login`).
 * - Politique tenant : `CompanySetting` clé `mfa_required_roles` (liste de
 *   rôles séparés par des virgules : `rh`, `principal`, `accountant`, …).
 */
final class TwoFactorAuthService
{
    public const CHALLENGE_TTL_SECONDS = 300;

    public function __construct(private readonly TotpService $totp) {}

    /**
     * État 2FA du compte (pour la UI et le challenge).
     *
     * @return array{enabled: bool, mfa_required: bool}
     */
    public function status(Employee $employee): array
    {
        return [
            'enabled' => $employee->two_fa_enabled_at !== null,
            'mfa_required' => $this->requiresMfa($employee),
        ];
    }

    /**
     * Démarre l'enrôlement : secret + URL du QR code.
     *
     * @return array{secret: string, qr_url: string}
     */
    public function enroll(Employee $employee): array
    {
        if ($employee->two_fa_enabled_at !== null) {
            throw TwoFactorException::alreadyEnabled();
        }

        $secret = $this->totp->generateSecret();
        $employee->forceFill(['two_fa_secret' => $secret])->save();

        return [
            'secret' => $secret,
            'qr_url' => $this->totp->qrCodeUrl($employee->email, $secret),
        ];
    }

    /**
     * Confirme l'enrôlement (vérifie un premier code) et active la 2FA.
     *
     * @return array{recovery_codes: list<string>}
     */
    public function confirm(Employee $employee, string $code): array
    {
        if ($employee->two_fa_enabled_at !== null) {
            throw TwoFactorException::alreadyEnabled();
        }

        $secret = (string) $employee->two_fa_secret;
        if (! $this->totp->verifyCode($secret, $code)) {
            throw TwoFactorException::invalidCode();
        }

        $codes = $this->generateRecoveryCodes();

        $employee->forceFill([
            'two_fa_enabled_at' => now(),
            'two_fa_recovery_codes' => $codes['hashed'],
        ])->save();

        return ['recovery_codes' => $codes['plain']];
    }

    /**
     * Désactive la 2FA (re-vérification par code TOTP requise).
     */
    public function disable(Employee $employee, string $code): void
    {
        if ($employee->two_fa_enabled_at === null) {
            return;
        }

        if (! $this->totp->verifyCode((string) $employee->two_fa_secret, $code)) {
            throw TwoFactorException::invalidCode();
        }

        $employee->forceFill([
            'two_fa_secret' => null,
            'two_fa_enabled_at' => null,
            'two_fa_recovery_codes' => null,
        ])->save();
    }

    /**
     * Régénère les codes de récupération (compte déjà enrôlé).
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(Employee $employee): array
    {
        if ($employee->two_fa_enabled_at === null) {
            throw TwoFactorException::invalidCode();
        }

        $codes = $this->generateRecoveryCodes();
        $employee->forceFill(['two_fa_recovery_codes' => $codes['hashed']])->save();

        return $codes['plain'];
    }

    /**
     * La politique tenant impose-t-elle la 2FA pour ce compte ?
     */
    public function requiresMfa(Employee $employee): bool
    {
        $setting = CompanySetting::query()
            ->where('key', 'mfa_required_roles')
            ->where('company_id', $employee->company_id)
            ->first();

        if ($setting === null || ! is_string($setting->value) || $setting->value === '') {
            return false;
        }

        $roles = array_filter(array_map('trim', explode(',', $setting->value)));

        if ($roles === []) {
            return false;
        }

        $employeeRoles = array_values(array_filter([
            $employee->role,
            $employee->manager_role,
        ], static fn (?string $r): bool => is_string($r) && $r !== ''));

        return count(array_intersect($roles, $employeeRoles)) > 0;
    }

    /**
     * Émet un challenge de connexion (cache, TTL 5 min).
     *
     * @param  array{employee_id: int, company_id: string, tenant_schema: string|null, email: string, device_name: string|null}  $context
     * @return array{token: string, expires_in: int}
     */
    public function issueChallenge(array $context): array
    {
        $token = Str::random(64);
        Cache::put('mfa:challenge:'.$token, $context, self::CHALLENGE_TTL_SECONDS);

        return ['token' => $token, 'expires_in' => self::CHALLENGE_TTL_SECONDS];
    }

    /**
     * Vérifie un challenge : code TOTP OU code de récupération, puis délivre
     * le token Sanctum (abilities tenant, mêmes règles qu'AuthService::login).
     *
     * @return array{token: string, token_type: string, token_expires_at: ?string, employee: Employee}
     */
    public function verifyChallenge(string $challengeToken, ?string $code, ?string $recoveryCode, ?string $deviceName = null): array
    {
        /** @var array{employee_id: int, company_id: string, tenant_schema: string|null, email: string, device_name: string|null}|null $context */
        $context = Cache::get('mfa:challenge:'.$challengeToken);

        if (! is_array($context) || ! isset($context['employee_id'])) {
            throw TwoFactorException::challengeExpired();
        }

        Cache::forget('mfa:challenge:'.$challengeToken);

        $previousSearchPath = null;
        if (DB::getDriverName() === 'pgsql' && is_string($context['tenant_schema'] ?? null) && $context['tenant_schema'] !== '') {
            $searchPathResult = DB::selectOne('SHOW search_path');
            $previousSearchPath = is_object($searchPathResult) ? (string) $searchPathResult->search_path : null;
            DB::statement('SET search_path TO '.$context['tenant_schema']);
        }

        try {
            /** @var Employee|null $employee */
            $employee = Employee::query()->find((int) $context['employee_id']);

            if ($employee === null || $employee->two_fa_enabled_at === null) {
                throw TwoFactorException::challengeExpired();
            }

            $verified = false;
            if (is_string($code) && $code !== '') {
                $verified = $this->totp->verifyCode((string) $employee->two_fa_secret, $code);
            }

            if (! $verified && is_string($recoveryCode) && $recoveryCode !== '') {
                $verified = $this->consumeRecoveryCode($employee, $recoveryCode);
            }

            if (! $verified) {
                throw TwoFactorException::invalidCode();
            }

            /** @var Company|null $company */
            $company = Company::query()->find($context['company_id']);

            if ($company === null || in_array($company->status, ['suspended', 'expired'], true)) {
                throw TwoFactorException::challengeExpired();
            }

            $employee->forceFill(['last_login_at' => now()])->saveQuietly();

            $tokenName = is_string($deviceName) && $deviceName !== '' ? $deviceName : ($context['device_name'] ?? 'api');
            $expirationMinutes = (int) config('sanctum.expiration', 0);
            $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
            $abilities = ['*'];
            if (is_string($context['tenant_schema'] ?? null) && $context['tenant_schema'] !== '') {
                $abilities[] = 'tenant_schema:'.$context['tenant_schema'];
                $abilities[] = 'tenant_email:'.$employee->email;
                $abilities[] = 'tenant_company:'.$company->id;
                $abilities[] = 'tenant_employee:'.$employee->id;
            }

            $tokenResult = $employee->createToken($tokenName, $abilities, $expiresAt);

            return [
                'token' => $tokenResult->plainTextToken,
                'token_type' => 'Bearer',
                'token_expires_at' => $expiresAt?->toIso8601String(),
                'employee' => $employee,
            ];
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }
    }

    /**
     * Cookie « remember device » : HMAC de l'identifiant + clé applicative.
     */
    public function rememberCookieValue(Employee $employee): string
    {
        return hash('sha256', $employee->id.'|'.(string) config('app.key').'|mfa-remember');
    }

    /**
     * @return array{plain: list<string>, hashed: list<string>} 8 codes (hachés en base)
     */
    private function generateRecoveryCodes(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(bin2hex(random_bytes(5)));
            $plain[] = $code;
            $hashed[] = hash('sha256', $code);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    private function consumeRecoveryCode(Employee $employee, string $code): bool
    {
        $stored = $employee->two_fa_recovery_codes;
        $hashed = is_array($stored) ? $stored : [];

        if ($hashed === []) {
            return false;
        }

        $candidate = hash('sha256', strtoupper($code));

        foreach ($hashed as $index => $entry) {
            if (is_string($entry) && hash_equals($entry, $candidate)) {
                unset($hashed[$index]);
                $employee->forceFill(['two_fa_recovery_codes' => array_values($hashed)])->save();

                return true;
            }
        }

        return false;
    }
}
