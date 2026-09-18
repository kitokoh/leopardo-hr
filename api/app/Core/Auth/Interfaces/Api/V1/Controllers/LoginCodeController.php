<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Mail\LoginCodeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * #7490 — connexion par code à usage unique (OTP) pour les comptes qui n'ont
 * JAMAIS défini de mot de passe (onboarding self-service : l'accès initial se
 * fait par le code de vérification, le mot de passe est optionnel en pratique).
 *
 * Périmètre volontairement étroit : seuls les comptes dont la ligne
 * `trial_provisionings` est `ready` avec `password_set_at` NULL sont
 * éligibles — un compte à mot de passe défini garde le chemin classique
 * (mot de passe + éventuelle 2FA). Réponse toujours générique côté demande
 * (anti-énumération d'e-mails, même posture que la réinitialisation).
 *
 * Le code (6 chiffres) vit 10 minutes en cache, HASHÉ (jamais en clair au
 * repos), consommé au premier usage, verrouillé après 5 échecs — même
 * politique que la vérification d'inscription (#6547).
 */
class LoginCodeController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthService $twoFactorService,
    ) {}

    /**
     * POST /api/v1/auth/login-code/request
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        // Réponse générique dans TOUS les cas : ne jamais révéler si un compte
        // existe ni s'il a déjà un mot de passe.
        $genericResponse = new JsonResponse([
            'success' => true,
            'localized_message' => __('auth.login_code_sent'),
        ]);

        $row = $this->eligibleProvisioningRow($email);

        if ($row === null) {
            return $genericResponse;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($email), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        try {
            Mail::to($email)->send(new LoginCodeMail($code, $this->resolveLocale($row)));
        } catch (\Throwable $e) {
            // Best-effort assumé côté réponse (générique), mais tracé : un
            // mailer en panne est indiagnosticable sans ce log (#3057).
            Log::error('auth.login_code_send_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $genericResponse;
    }

    /**
     * POST /api/v1/auth/login-code/verify
     *
     * Succès : même contrat que POST /auth/login (EmployeeResource + token) —
     * le proxy Next.js pose le cookie httpOnly exactement comme au login.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $key = $this->cacheKey($email);

        /** @var array{hash: string, attempts: int}|null $entry */
        $entry = Cache::get($key);

        if (! is_array($entry) || ! isset($entry['hash'])) {
            return $this->invalidCodeResponse();
        }

        $attempts = (int) ($entry['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return new JsonResponse([
                'success' => false,
                'error' => 'LOGIN_CODE_TOO_MANY_ATTEMPTS',
                'localized_message' => __('auth.login_code_too_many_attempts'),
            ], 429);
        }

        if (! Hash::check((string) $validated['code'], (string) $entry['hash'])) {
            $entry['attempts'] = $attempts + 1;
            Cache::put($key, $entry, now()->addMinutes(self::CODE_TTL_MINUTES));

            if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
                Log::warning('auth.login_code_locked', ['attempts' => $entry['attempts']]);
            }

            return $this->invalidCodeResponse();
        }

        // Usage unique : le code est consommé AVANT l'ouverture de session.
        Cache::forget($key);

        // L'éligibilité est re-vérifiée au moment du verify : si un mot de
        // passe a été défini entre-temps, le canal OTP se referme.
        if ($this->eligibleProvisioningRow($email) === null) {
            return $this->invalidCodeResponse();
        }

        try {
            $result = $this->authService->loginViaEmail($email, 'login-code');
        } catch (\Throwable $e) {
            Log::warning('auth.login_code_login_failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->invalidCodeResponse();
        }

        $employee = $result['employee'];

        // Un compte 2FA ne contourne JAMAIS son challenge par le canal OTP :
        // on révoque le token qui vient d'être créé et on renvoie vers le
        // chemin mot de passe + 2FA (posture #5436).
        if ($employee->two_fa_enabled_at !== null || $this->twoFactorService->requiresMfa($employee)) {
            $employee->tokens()->latest('id')->first()?->delete();

            return new JsonResponse([
                'success' => false,
                'error' => 'LOGIN_CODE_PASSWORD_REQUIRED',
                'localized_message' => __('auth.login_code_password_required'),
            ], 403);
        }

        return (new EmployeeResource($employee))
            ->additional([
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'token_expires_at' => $result['token_expires_at'],
            ])
            ->response();
    }

    /**
     * Ligne trial_provisionings éligible : espace prêt, mot de passe jamais
     * défini. C'est LA définition de « compte sans mot de passe » côté
     * self-service (même source que /trial/status et /trial/set-password).
     */
    private function eligibleProvisioningRow(string $email): ?object
    {
        try {
            $row = DB::table('trial_provisionings')
                ->where('email', $email)
                ->where('status', 'ready')
                ->whereNull('password_set_at')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('auth.login_code_provisioning_lookup_failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $row;
    }

    private function resolveLocale(object $row): string
    {
        try {
            $language = DB::table('companies')
                ->where('id', $row->company_id ?? null)
                ->value('language');
        } catch (\Throwable) {
            $language = null;
        }

        return in_array($language, ['fr', 'en', 'ar', 'tr'], true) ? $language : 'fr';
    }

    private function cacheKey(string $email): string
    {
        return 'auth:login_code:'.hash('sha256', $email);
    }

    private function invalidCodeResponse(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => 'LOGIN_CODE_INVALID',
            'localized_message' => __('auth.login_code_invalid'),
        ], 400);
    }
}
