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
    private const TWO_FA_PENDING_SESSION_KEY = 'platform_2fa_pending';

    private const TWO_FA_PENDING_TTL_SECONDS = 300;

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

        // Issue #6530 : un super-admin suspendu/desactive ne peut pas ouvrir
        // de session web (alignement sur le login API, garde #2630).
        if ($superAdmin->status !== 'active') {
            Log::channel('audit')->warning('platform_login.blocked_inactive', [
                'email' => $validated['email'],
                'status' => $superAdmin->status,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withInput(['email' => $validated['email']])->withErrors([
                'email' => 'Compte inactif.',
            ]);
        }

        // Issue #6530 : challenge TOTP quand un secret 2FA existe (alignement
        // sur le login API) — la session n'est ouverte qu'apres verification
        // du code. L'etat « en attente de 2FA » est borne (5 min) et n'ouvre
        // aucune session : il ne sert qu'a porter l'identite a verifier.
        if ($superAdmin->two_fa_secret) {
            $request->session()->put(self::TWO_FA_PENDING_SESSION_KEY, [
                'super_admin_id' => $superAdmin->id,
                'expires_at' => time() + self::TWO_FA_PENDING_TTL_SECONDS,
            ]);
            $request->session()->put('platform_2fa_email', $superAdmin->email);

            return redirect()->route('platform.login.2fa');
        }

        $this->loginSuperAdmin($request, $superAdmin);

        return redirect()->route('platform.companies.index');
    }

    public function show2fa(Request $request): View|RedirectResponse
    {
        if ($this->pending2fa($request) === null) {
            return redirect()->route('platform.login');
        }

        return view('platform.auth.twofa', [
            'email' => (string) $request->session()->get('platform_2fa_email', ''),
        ]);
    }

    public function verify2fa(Request $request): RedirectResponse
    {
        $pending = $this->pending2fa($request);

        if ($pending === null) {
            return redirect()->route('platform.login');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::query()->find($pending['super_admin_id']);

        if (! $superAdmin || ! $this->superAdminService->verifyCode($superAdmin, $validated['code'])) {
            Log::channel('audit')->warning('platform_login.2fa_failed', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'code' => 'Code invalide.',
            ]);
        }

        $request->session()->forget(self::TWO_FA_PENDING_SESSION_KEY);
        $request->session()->forget('platform_2fa_email');

        $this->loginSuperAdmin($request, $superAdmin);

        return redirect()->route('platform.companies.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('super_admin_web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }

    /**
     * Retourne l'etat 2FA en attente si valide et non expire, sinon null
     * (et nettoie la session dans ce cas).
     *
     * @return array{super_admin_id: int, expires_at: int}|null
     */
    private function pending2fa(Request $request): ?array
    {
        $pending = $request->session()->get(self::TWO_FA_PENDING_SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['super_admin_id'], $pending['expires_at'])) {
            return null;
        }

        if ((int) $pending['expires_at'] < time()) {
            $request->session()->forget(self::TWO_FA_PENDING_SESSION_KEY);
            $request->session()->forget('platform_2fa_email');

            return null;
        }

        return $pending;
    }

    private function loginSuperAdmin(Request $request, SuperAdmin $superAdmin): void
    {
        Auth::guard('super_admin_web')->login($superAdmin);
        $request->session()->regenerate();
    }
}
