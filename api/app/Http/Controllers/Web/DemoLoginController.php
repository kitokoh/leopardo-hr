<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Issue #2253 — Magic link d'accès au sandbox de démo.
 *
 * GET /demo-login/{token} — lien à usage unique émis par
 * `ProvisionDemoTenantJob` (le hash SHA-256 du jeton et son expiration sont
 * stockés dans `employees.extra_data`). Valide le jeton, connecte l'employé
 * en session web (guard `web`, même flux que WebAuthController::login) puis
 * redirige vers le dashboard.
 */
class DemoLoginController extends Controller
{
    private const TOKEN_HASH_KEY = 'demo_access_token_hash';

    private const TOKEN_EXPIRES_KEY = 'demo_access_token_expires_at';

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);

        // #3727 : lookup cross-tenant volontaire (token de démo hashé, aucune
        // connaissance du tenant avant résolution) — opt-out explicite du scope.
        $employee = Employee::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('extra_data->'.self::TOKEN_HASH_KEY, $hash)
            ->first();

        if ($employee === null) {
            Log::channel('audit')->warning('demo_login.invalid_token', [
                'ip' => $request->ip(),
            ]);

            return redirect('/login')->withErrors(['email' => __('auth.demo_link_invalid')]);
        }

        $expiresAt = $employee->extra_data[self::TOKEN_EXPIRES_KEY] ?? null;
        if (! is_string($expiresAt) || Carbon::parse($expiresAt)->isPast()) {
            Log::channel('audit')->warning('demo_login.expired_token', [
                'employee_id' => $employee->id,
                'ip' => $request->ip(),
            ]);

            return redirect('/login')->withErrors(['email' => __('auth.demo_link_expired')]);
        }

        // Le jeton est à usage unique : on le révoque avant de connecter.
        $extraData = $employee->extra_data;
        unset($extraData[self::TOKEN_HASH_KEY], $extraData[self::TOKEN_EXPIRES_KEY]);
        $employee->update(['extra_data' => $extraData]);

        Auth::guard('web')->login($employee);
        $request->session()->regenerate();

        Log::channel('audit')->info('demo_login.success', [
            'employee_id' => $employee->id,
            'ip' => $request->ip(),
        ]);

        return redirect('/dashboard');
    }
}
