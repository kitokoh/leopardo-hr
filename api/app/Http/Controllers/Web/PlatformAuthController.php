<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PlatformAuthController extends Controller
{
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

