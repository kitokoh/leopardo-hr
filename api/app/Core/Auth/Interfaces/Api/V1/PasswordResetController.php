<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Mail\PasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Issue #2626 — réinitialisation de mot de passe.
 *
 * POST /auth/forgot-password : émission d'un jeton à usage unique (60 min),
 * réponse générique anti-énumération.
 * POST /auth/reset-password : validation du jeton + nouveau mot de passe,
 * révocation des tokens Sanctum existants.
 */
class PasswordResetController
{
    private const TOKEN_TTL_MINUTES = 60;

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        // #3363 : les employés des tenants à schéma vivent dans leur schéma —
        // une résolution directe sans SET search_path ne les trouve jamais
        // (search_path prod = shared_tenants,public). Même pattern qu'AuthService
        // (public.user_lookups → schéma → employé).
        $employee = $this->resolveEmployeeForEmail($email);

        // Anti-énumération : même réponse que l'email existe ou non.
        if ($employee !== null) {
            $token = Str::random(64);

            DB::table('public.password_reset_tokens')->insert([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
                'used_at' => null,
                'created_at' => now(),
            ]);

            Mail::to($email)->send(new PasswordResetMail($token, $email));
        }

        return new JsonResponse([
            'message' => 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));
        $tokenHash = hash('sha256', $validated['token']);

        $row = DB::table('public.password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->first();

        // Token absent, expiré ou déjà consommé → refus générique (422).
        if ($row === null || $row->used_at !== null || now()->greaterThan($row->expires_at)) {
            return new JsonResponse([
                'message' => 'Jeton de réinitialisation invalide, expiré ou déjà utilisé.',
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        $employee = $this->resolveEmployeeForEmail($email);

        if ($employee === null) {
            return new JsonResponse([
                'message' => 'Jeton de réinitialisation invalide, expiré ou déjà utilisé.',
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        // Consommation du jeton (idempotence) puis mise à jour du mot de passe.
        DB::table('public.password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->update(['used_at' => now()]);

        $employee->update(['password_hash' => Hash::make($validated['password'])]);

        // Issue #2626 : révocation des sessions existantes (tokens Sanctum).
        $employee->tokens()->delete();

        return new JsonResponse([
            'message' => 'Mot de passe réinitialisé. Connectez-vous avec votre nouveau mot de passe.',
        ]);
    }

    /**
     * Résout l'employé propriétaire d'un email, y compris pour les tenants à
     * schéma (pattern AuthService, #3363) :
     * 1. lookup cross-schéma `public.user_lookups` → (schema_name, company_id, employee_id)
     * 2. `SET search_path TO <schema>, public` si le schéma est sûr
     * 3. chargement de l'employé par company_id + id (jamais par email dans un
     *    schéma inconnu — un employé d'un autre tenant pourrait matcher)
     * Repli : recherche directe par email (couvre shared_tenants et la base public).
     */
    private function resolveEmployeeForEmail(string $email): ?Employee
    {
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;
        $lookup = null;

        try {
            if (DB::getDriverName() === 'pgsql') {
                $exists = DB::selectOne("select to_regclass('public.user_lookups') as table_name");
                if (is_object($exists) && property_exists($exists, 'table_name') && $exists->table_name !== null) {
                    $lookup = DB::table('public.user_lookups')->where('email', $email)->first();
                }
            } elseif (Schema::hasTable('user_lookups')) {
                $lookup = DB::table('user_lookups')->where('email', $email)->first();
            }

            if ($lookup !== null) {
                $schema = is_string($lookup->schema_name ?? null) ? $lookup->schema_name : null;
                if (is_string($schema) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
                    DB::statement('SET search_path TO '.$this->quoteIdentifier($schema).', public');
                }

                /** @var Employee|null $employee */
                $employee = Employee::withoutGlobalScopes()
                    ->where('company_id', $lookup->company_id)
                    ->where('id', $lookup->employee_id)
                    ->first();

                if ($employee !== null) {
                    return $employee;
                }
            }
        } catch (QueryException $e) {
            // #3363 : un lookup vers un schéma absent/migration partielle ne doit
            // jamais faire échouer le flux en 500 — même traitement qu'AuthService
            // (#2652) : journaliser et retomber sur la résolution directe.
            Log::warning('password_reset.employee_resolution_failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
        } finally {
            if ($previousSearchPath !== null) {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }

        return Employee::withoutGlobalScopes()->where('email', $email)->first();
    }

    private function currentSearchPath(): ?string
    {
        $result = DB::selectOne('SHOW search_path');

        return is_object($result) && property_exists($result, 'search_path') ? (string) $result->search_path : null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
