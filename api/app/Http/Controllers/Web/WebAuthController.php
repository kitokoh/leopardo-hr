<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TotpService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Core\Auth\Domain\Exceptions\TwoFactorException
use App\Core\Auth\Infrastructure\Services\AuthService
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService
use Carbon\Carbon
use Throwable;

class WebAuthController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private readonly TotpService $totpService,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'two_fa_code' => ['nullable', 'string'],
        ]);

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $employee || ! Hash::check($validated['password'], $employee->password_hash)) {
            // audit(securite) #6541 : verrouillage de compte — parité avec
            // AuthService::login (#2973). Un compte verrouillé (locked_until
            // futur) ne peut plus essayer, même avec le bon mot de passe.
            if ($employee && $this->supportsLoginLocking($employee)) {
                $this->recordFailedAttempt($employee);
            }
            // PA2-API-005: security-relevant event, logged to the dedicated
            // 'audit' channel so brute-force attempts against the employee web
            // login are visible independently of the per-minute throttle.
            Log::channel('audit')->warning('web_login.failed', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()
                ->withInput(['email' => $validated['email']])
                ->withErrors(['email' => 'Identifiants invalides.']);
        }

        // audit(securite) #6541 : un compte verrouillé ne peut pas se connecter,
        // même avec des identifiants valides — parité AuthService (#2973).
        if ($this->supportsLoginLocking($employee)) {
            $lockedUntil = $this->lockedUntil($employee);
            if ($lockedUntil !== null && $lockedUntil->isFuture()) {
                Log::channel('audit')->warning('web_login.locked', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'locked_until' => $lockedUntil->toDateTimeString(),
                ]);

                return back()
                    ->withInput(['email' => $validated['email']])
                    ->withErrors(['email' => __('errors.ACCOUNT_LOCKED_TEMPORARILY')]);
            }
        }

        // audit(securite) #6541 : challenge TOTP pour les comptes enrôlés —
        // parité avec le flux API AuthController::login (#5436). Sans ce
        // garde, la surface session contournait la 2FA.
        if ($employee->two_fa_enabled_at !== null) {
            $code = is_string($validated['two_fa_code'] ?? null) ? trim($validated['two_fa_code']) : '';

            if ($code === '' || ! $this->totpService->verifyCode((string) $employee->two_fa_secret, $code)) {
                Log::channel('audit')->warning('web_login.twofa_failed', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return back()
                    ->withInput(['email' => $validated['email'], 'two_fa_required' => true])
                    ->withErrors(['two_fa_code' => $code === '' ? __('auth.twofa_code_required') : __('auth.twofa_code_invalid')]);
            }
        }

        // Succès : réinitialisation du compteur (parité AuthService).
        if ($this->supportsLoginLocking($employee)) {
            $this->resetFailedAttempts($employee);
        }

        if ($employee->status !== 'active') {
            return back()
                ->withInput(['email' => $validated['email']])
                // #4878 : littéral FR déplacé au catalogue errors.*
                ->withErrors(['email' => __('errors.EMPLOYEE_INACTIVE')]);
        }

        if (in_array($employee->company?->status, ['suspended', 'expired'], true)) {
            return back()
                ->withInput(['email' => $validated['email']])
                // #4878 : littéral FR déplacé au catalogue errors.*
                ->withErrors(['email' => __('errors.COMPANY_SUSPENDED_EXPIRED')]);
        }

        Auth::guard('web')->login($employee);
        $request->session()->regenerate();

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

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
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

    private function recordFailedAttempt(Employee $employee): void
    {
        $employee->increment('failed_login_attempts');
        if ($employee->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $employee->forceFill(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)])->save();
        }
    }

    private function resetFailedAttempts(Employee $employee): void
    {
        if ($employee->failed_login_attempts > 0 || $employee->locked_until !== null) {
            $employee->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }
    }

    private function lockedUntil(Employee $employee): ?Carbon
    {
        $raw = $employee->getAttributes()['locked_until'] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return $raw instanceof \DateTimeInterface ? Carbon::instance($raw) : null;
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
}