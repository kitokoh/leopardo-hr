<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Domain\Exceptions\TwoFactorException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Connexion web (session) des employés/managers.
 *
 * #6541 — la surface web doit appliquer la même politique que l'API :
 * résolution via `public.user_lookups`, verrouillage après 5 échecs
 * (15 min), et challenge TOTP quand le compte a la 2FA activée. Avant, un
 * compte 2FA se connectait sans code et sans jamais être verrouillé.
 */
class WebAuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthService $twoFactor,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showTwoFactorChallenge(): View|RedirectResponse
    {
        // #6541 — l'accès direct à la page challenge sans login en cours est
        // redirigé vers le login (pas de formulaire orphelin exploitable).
        $token = session('pending_2fa_challenge');

        if (! is_string($token) || $token === '') {
            return redirect()->route('login');
        }

        return view('auth.2fa-challenge', ['challengeToken' => $token]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // #6541 — résolution alignée sur l'API (lookup public.user_lookups,
        // schéma tenant, fallback search_path) au lieu du simple where(email).
        $employee = $this->authService->resolveEmployeeForLogin($validated['email']);

        if (! $employee) {
            $this->logFailure($request, $validated['email']);

            return $this->backWithError($request, $validated['email'], 'Identifiants invalides.');
        }

        // #6541 — verrouillage de compte (mêmes colonnes/contrat que l'API) :
        // locked_until dans le futur → refus avant même la vérification du mot
        // de passe.
        $lockedUntil = $this->lockedUntil($employee);
        if ($this->supportsLoginLocking($employee)
            && $lockedUntil instanceof Carbon
            && $lockedUntil->isFuture()) {
            $this->logFailure($request, $validated['email'], 'locked');

            return $this->backWithError($request, $validated['email'], __('auth.account_locked'));
        }

        // Password null/absent ou hash malformé = identifiants invalides
        // (jamais un 500), même traitement que l'API (#2652/#2973).
        $passwordMatches = false;
        if (is_string($employee->password_hash) && $employee->password_hash !== '') {
            try {
                $passwordMatches = Hash::check($validated['password'], $employee->password_hash);
            } catch (Throwable) {
                $passwordMatches = false;
            }
        }

        if (! $passwordMatches) {
            if ($this->supportsLoginLocking($employee)) {
                $employee->increment('failed_login_attempts');
                if ($employee->failed_login_attempts >= 5) {
                    $employee->locked_until = now()->addMinutes(15);
                    $employee->save();
                    $this->logFailure($request, $validated['email'], 'locked');
                }
            }
            $this->logFailure($request, $validated['email']);

            return $this->backWithError($request, $validated['email'], 'Identifiants invalides.');
        }

        // Reset des compteurs sur succès (contrat API).
        if ($this->supportsLoginLocking($employee) && ($employee->failed_login_attempts > 0 || $employee->locked_until)) {
            $employee->failed_login_attempts = 0;
            $employee->locked_until = null;
            $employee->save();
        }

        if ($employee->status !== 'active') {
            return $this->backWithError($request, $validated['email'], __('errors.EMPLOYEE_INACTIVE'));
        }

        if (in_array($employee->company?->status, ['suspended', 'expired'], true)) {
            return $this->backWithError($request, $validated['email'], __('errors.COMPANY_SUSPENDED_EXPIRED'));
        }

        // #6541 — 2FA activée : challenge TOTP obligatoire avant la session
        // (plus de contournement de la 2FA par la surface web).
        if ($employee->two_fa_enabled_at !== null) {
            $challenge = $this->twoFactor->issueChallenge([
                'employee_id' => $employee->id,
                'company_id' => (string) $employee->company_id,
                'tenant_schema' => null,
                'email' => $employee->email,
                'device_name' => 'web-session',
            ]);

            $request->session()->put('pending_2fa_challenge', $challenge['token']);

            return redirect()->route('login.2fa');
        }

        Auth::guard('web')->login($employee);
        $request->session()->regenerate();

        return $this->homeRedirect($request, $employee);
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $token = session('pending_2fa_challenge');
        if (! is_string($token) || $token === '') {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required_without:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['sometimes', 'string', 'max:32'],
        ]);

        try {
            $result = $this->twoFactor->verifyWebChallenge(
                $token,
                isset($validated['code']) && $validated['code'] !== '' ? $validated['code'] : null,
                isset($validated['recovery_code']) && $validated['recovery_code'] !== '' ? $validated['recovery_code'] : null,
            );
        } catch (TwoFactorException) {
            return back()->withErrors(['code' => __('auth.twofa_code_invalid')]);
        }

        $employee = $result['employee'];

        if ($employee->status !== 'active') {
            return redirect()->route('login')->withErrors(['email' => __('errors.EMPLOYEE_INACTIVE')]);
        }

        $request->session()->forget('pending_2fa_challenge');

        Auth::guard('web')->login($employee);
        $request->session()->regenerate();

        return $this->homeRedirect($request, $employee);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function homeRedirect(Request $request, Employee $employee): RedirectResponse
    {
        $home = route($employee->homeRoute());

        // Les managers peuvent reprendre l'URL demandee avant login (intended),
        // mais un simple employe doit toujours atterrir sur son espace /me :
        // sinon un /dashboard stocke dans la session provoque un 403 sans
        // bouton de navigation pour en sortir.
        if ($employee->isManager()) {
            return redirect()->intended($home);
        }

        $request->session()->forget('url.intended');

        return redirect()->to($home);
    }

    private function backWithError(Request $request, string $email, string $message): RedirectResponse
    {
        return back()
            ->withInput(['email' => $email])
            ->withErrors(['email' => $message]);
    }

    private function logFailure(Request $request, string $email, string $reason = 'invalid_credentials'): void
    {
        Log::channel('audit')->warning('web_login.failed', [
            'email' => $email,
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * #6541 — verrouillage de compte : parse la valeur BRUTE de locked_until
     * (getAttributes, même piège que l'API #2973) en Carbon.
     */
    private function lockedUntil(Employee $employee): ?Carbon
    {
        $raw = $employee->getAttributes()['locked_until'] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                $parsed = Carbon::parse($raw);

                return $parsed;
            } catch (Throwable) {
                return null;
            }
        }

        if ($raw instanceof \DateTimeInterface) {
            $parsed = Carbon::instance($raw);

            return $parsed;
        }

        return null;
    }

    private function supportsLoginLocking(Employee $employee): bool
    {
        $connection = $employee->getConnection();
        $table = $employee->getTable();

        return Schema::connection($connection->getName())->hasColumns($table, [
            'failed_login_attempts',
            'locked_until',
        ]);
    }
}
