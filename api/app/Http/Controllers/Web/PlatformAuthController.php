<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Infrastructure\Services\SuperAdminService;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PlatformAuthController extends Controller
{
    public function __construct(
        private readonly SuperAdminService $superAdminService,
    ) {}

    public function showLogin(): View
    {
        return view('platform.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'two_fa_code' => ['nullable', 'string'],
        ]);

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::query()->where('email', $validated['email'])->first();

        if (! $superAdmin || ! Hash::check($validated['password'], $superAdmin->password_hash)) {
            // PA2-API-005: security-relevant event, logged to the dedicated
            // 'audit' channel so brute-force attempts against the super-admin
            // login are visible independently of the per-minute throttle.
            Log::channel('audit')->warning('platform_login.failed', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withInput(['email' => $validated['email']])->withErrors([
                'email' => 'Identifiants invalides.',
            ]);
        }

        // #6530 — aligné sur l'API (Core/Auth PlatformAuthController, #2630) :
        // un super-admin suspendu ou désactivé ne peut pas se connecter par
        // cette surface web non plus (il pouvait avant, contournant la garde API).
        if ($superAdmin->status !== 'active') {
            Log::channel('audit')->warning('platform_login.blocked_inactive', [
                'email' => $validated['email'],
                'status' => $superAdmin->status,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withInput(['email' => $validated['email']])->withErrors([
                'email' => 'Compte suspendu ou désactivé. Contactez un administrateur.',
            ]);
        }

        // #6530 — aligné sur l'API : le TOTP est obligatoire quand two_fa_secret
        // est défini. La vue affiche le champ 2FA uniquement quand il est requis.
        if ($superAdmin->two_fa_secret) {
            $twoFaCode = $validated['two_fa_code'] ?? null;

            if ($twoFaCode === null) {
                return back()->withInput(['email' => $validated['email']])
                    ->with('two_fa_required', true)
                    ->withErrors(['two_fa_code' => 'Code 2FA requis.']);
            }

            if (! $this->superAdminService->verifyCode($superAdmin, $twoFaCode)) {
                Log::channel('audit')->warning('platform_login.2fa_failed', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return back()->withInput(['email' => $validated['email']])
                    ->with('two_fa_required', true)
                    ->withErrors(['two_fa_code' => 'Code 2FA invalide.']);
            }
        }

        Auth::guard('super_admin_web')->login($superAdmin);
        $request->session()->regenerate();

        Log::channel('audit')->info('platform_login.success', [
            'email' => $validated['email'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('platform.companies.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('super_admin_web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
