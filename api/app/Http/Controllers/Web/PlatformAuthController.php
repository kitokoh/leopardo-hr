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

        // audit(securite) #6530 : un super-admin suspendu/désactivé ne peut pas
        // se connecter par la surface web — parité avec l'API jumelle
        // (Core/Auth/.../PlatformAuthController, sécurité #2630).
        if ($superAdmin->status !== 'active') {
            Log::channel('audit')->warning('platform_login.suspended', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withInput(['email' => $validated['email']])->withErrors([
                'email' => __('errors.ACCOUNT_SUSPENDED'),
            ]);
        }

        // audit(securite) #6530 : challenge TOTP quand le secret existe — parité
        // avec l'API jumelle. Un mot de passe seul ne suffit plus à piloter la
        // plateforme quand la 2FA est activée.
        if ($superAdmin->two_fa_secret) {
            $code = is_string($validated['two_fa_code'] ?? null) ? trim($validated['two_fa_code']) : '';

            if ($code === '') {
                Log::channel('audit')->warning('platform_login.twofa_required', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return back()->withInput([
                    'email' => $validated['email'],
                    'two_fa_required' => true,
                ])->withErrors([
                    'two_fa_code' => __('auth.twofa_code_required'),
                ]);
            }

            if (! $this->superAdminService->verifyCode($superAdmin, $code)) {
                Log::channel('audit')->warning('platform_login.twofa_failed', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return back()->withInput([
                    'email' => $validated['email'],
                    'two_fa_required' => true,
                ])->withErrors([
                    'two_fa_code' => __('auth.twofa_code_invalid'),
                ]);
            }
        }

        Auth::guard('super_admin_web')->login($superAdmin);
        $request->session()->regenerate();

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
