<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Mail\PasswordResetMail;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Issue #2626 — réinitialisation de mot de passe.
 *
 * POST /auth/forgot-password : émission d'un jeton à usage unique (60 min),
 * réponse générique anti-énumération.
 * POST /auth/reset-password : validation du jeton + nouveau mot de passe,
 * révocation des tokens Sanctum existants.
 *
 * #3363 — la résolution de l'employé doit être tenant-aware : les employés des
 * tenants à schéma vivent dans leur schéma, pas dans `shared_tenants`. On
 * réutilise le pattern `AuthService::login` (public.user_lookups →
 * SET search_path → chargement/update) avec restauration du search_path dans
 * un finally.
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

        [$employee] = $this->resolveEmployeeAnywhere($email);

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

        [$employee, $employeeSchema] = $this->resolveEmployeeAnywhere($email);

        if ($employee === null) {
            return new JsonResponse([
                'message' => 'Jeton de réinitialisation invalide, expiré ou déjà utilisé.',
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        // Consommation du jeton (idempotence).
        DB::table('public.password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->update(['used_at' => now()]);

        // Mise à jour du mot de passe DANS le contexte tenant de l'employé
        // (un employé de tenant à schéma vit dans son schéma — le search_path
        // doit y pointer pendant l'UPDATE et la révocation des tokens).
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;

        try {
            if ($employeeSchema !== null) {
                $this->setTenantSearchPath($employeeSchema);
            }

            $employee->update(['password_hash' => Hash::make($validated['password'])]);

            // Révocation des tokens Sanctum existants (sécurité — même contrat
            // qu'un changement de mot de passe volontaire).
            $employee->tokens()->delete();
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }

        return new JsonResponse([
            'message' => 'Votre mot de passe a été réinitialisé.',
        ]);
    }

    /**
     * Résout un employé par email dans TOUS les tenants (shared + schémas),
     * avec restauration du search_path. Même pattern que AuthService::login.
     */
    /**
     * @return array{0: Employee|null, 1: string|null} — [employé, schéma tenant éventuel]
     */
    private function resolveEmployeeAnywhere(string $email): array
    {
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;
        $employee = null;
        $employeeSchema = null;

        try {
            $lookup = null;
            if ($this->lookupTableExists()) {
                $lookup = DB::table($this->lookupTable())
                    ->where('email', $email)
                    ->first();
            }

            if ($lookup) {
                $lookupSchema = is_string($lookup->schema_name ?? null) ? $lookup->schema_name : null;

                if ($lookupSchema !== null && $this->isSafeSchemaName($lookupSchema)) {
                    $this->setTenantSearchPath($lookupSchema);
                    $employeeSchema = $lookupSchema;
                }

                // #2652 : schéma tenant absent/migré partiel ⇒ traité « aucun employé ».
                if ($lookupSchema === null || $this->tenantEmployeesTableExists($lookupSchema)) {
                    /** @var Employee|null $employee */
                    $employee = Employee::withoutGlobalScopes()
                        ->where('company_id', $lookup->company_id)
                        ->where('id', $lookup->employee_id)
                        ->where('email', $email)
                        ->first();
                }
            }

            if (! $employee) {
                [$employee, $employeeSchema] = $this->findEmployeeInTenantSchemas($email);
            }

            if (! $employee) {
                /** @var Employee|null $employee */
                $employee = Employee::withoutGlobalScopes()
                    ->where('email', $email)
                    ->first();
            }
        } catch (QueryException $e) {
            // Jamais de 500 sur la résolution (schéma absent, table partielle).
            Log::warning('auth.password_reset_employee_resolution_failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return [null, null];
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }

        return [$employee, $employeeSchema];
    }

    /**
     * Cherche l'employé dans tous les schémas tenants connus (fallback quand
     * le lookup est absent ou périmé).
     */
    /**
     * @return array{0: Employee|null, 1: string|null}
     */
    private function findEmployeeInTenantSchemas(string $email): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [null, null];
        }

        $schemas = DB::table('public.companies')
            ->whereNotNull('schema_name')
            ->pluck('schema_name')
            ->filter(fn (mixed $schema): bool => is_string($schema) && $this->isSafeSchemaName($schema))
            ->unique()
            ->values();

        if ($schemas->isEmpty()) {
            return [null, null];
        }

        $previous = $this->currentSearchPath();

        try {
            foreach ($schemas as $schema) {
                if (! $this->tenantEmployeesTableExists((string) $schema)) {
                    continue;
                }

                $this->setTenantSearchPath((string) $schema);

                /** @var Employee|null $employee */
                $employee = Employee::withoutGlobalScopes()
                    ->where('email', $email)
                    ->first();

                if ($employee instanceof Employee) {
                    return [$employee, (string) $schema];
                }
            }
        } finally {
            if ($previous !== null && $previous !== '') {
                DB::statement('SET search_path TO '.$previous);
            }
        }

        return [null, null];
    }

    private function lookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }

    private function lookupTableExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('user_lookups');
        }

        $table = DB::selectOne("select to_regclass('public.user_lookups') as table_name");

        return $table?->table_name !== null;
    }

    private function tenantEmployeesTableExists(string $schema): bool
    {
        $table = DB::selectOne('select to_regclass(?) as table_name', [$schema.'.employees']);

        return $table?->table_name !== null;
    }

    private function isSafeSchemaName(string $schema): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $schema) === 1;
    }

    private function currentSearchPath(): ?string
    {
        $result = (array) DB::selectOne('SHOW search_path');

        return isset($result['search_path']) ? (string) $result['search_path'] : null;
    }

    private function setTenantSearchPath(string $schema): void
    {
        DB::statement('SET search_path TO '.$schema.',public');
    }
}
