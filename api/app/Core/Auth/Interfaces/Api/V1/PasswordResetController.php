<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Audit expert 2026-08-15 (issue #2626) : flux forgot/reset password.
 *
 * L'API n'offrait aucun moyen de réinitialiser un mot de passe oublié.
 * - POST /auth/forgot-password : lookup cross-tenant (public.user_lookups),
 *   token haché 60 min stocké dans public.password_reset_tokens, email envoyé.
 *   Réponse générique 200 (anti-énumération d'emails).
 * - POST /auth/reset-password : token + email + nouveau mot de passe ;
 *   bascule le search_path du tenant, met à jour le hash, révoque les tokens
 *   Sanctum existants, token à usage unique.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $email = strtolower(trim($validated['email']));
        $lookup = $this->findLookup($email);

        // Toujours 200 : ne jamais révéler si un email est enregistré.
        if ($lookup === null) {
            return new JsonResponse(['success' => true, 'message' => 'PASSWORD_RESET_SENT']);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'company_id' => $lookup->company_id,
            'employee_id' => $lookup->employee_id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $resetUrl = $frontendUrl.'/reset-password?token='.$token.'&email='.rawurlencode($email);

        try {
            Mail::to($email)->send(new PasswordResetMail($resetUrl));
        } catch (\Throwable $e) {
            // #1776 pattern : un mailer absent ne doit pas faire échouer le flux
            // principal — le token reste valide 60 min et l'envoi est loggé.
            Log::warning('Password reset email could not be sent', ['email' => $email, 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => true, 'message' => 'PASSWORD_RESET_SENT']);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));
        $tokenHash = hash('sha256', $validated['token']);

        /** @var object{id: int, email: string, company_id: string, employee_id: int, token_hash: string, expires_at: string, used_at: string|null}|null $row */
        $row = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->first();

        if ($row === null || $row->used_at !== null || Carbon::parse($row->expires_at)->isPast()) {
            abort(422, 'PASSWORD_RESET_TOKEN_INVALID');
        }

        /** @var Company|null $company */
        $company = Company::query()->find($row->company_id);
        if ($company === null) {
            abort(422, 'PASSWORD_RESET_TOKEN_INVALID');
        }

        $updated = $this->tenantManager->withinTenant($company, function () use ($row, $validated): bool {
            /** @var Employee|null $employee */
            $employee = Employee::withoutGlobalScopes()->find($row->employee_id);

            if ($employee === null) {
                return false;
            }

            $employee->forceFill([
                'password_hash' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ])->saveQuietly();

            // Révocation des tokens Sanctum existants (sécurité : l'ancien
            // mot de passe ne doit plus fonctionner nulle part).
            $employee->tokens()->delete();

            DB::table('password_reset_tokens')
                ->where('id', $row->id)
                ->update(['used_at' => now(), 'updated_at' => now()]);

            return true;
        });

        if (! $updated) {
            abort(422, 'PASSWORD_RESET_TOKEN_INVALID');
        }

        return new JsonResponse(['success' => true, 'message' => 'PASSWORD_RESET_DONE']);
    }

    /**
     * @return object{company_id: string, employee_id: int}|null
     */
    private function findLookup(string $email): ?object
    {
        if (DB::getDriverName() === 'pgsql') {
            $reg = DB::selectOne("select to_regclass('public.user_lookups') as t");
            if ($reg === null || $reg->t === null) {
                return null;
            }
        } elseif (! DB::getSchemaBuilder()->hasTable('user_lookups')) {
            return null;
        }

        /** @var object{company_id: string, employee_id: int}|null $lookup */
        $lookup = DB::table('public.user_lookups')->where('email', $email)->first();

        return $lookup;
    }
}
