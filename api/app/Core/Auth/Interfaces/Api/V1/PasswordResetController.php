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
use stdClass;
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

        // #4495 : chemin public — résolution via user_lookups uniquement
        // (pas de balayage multi-schéma : absence = aucun compte, temps ≈ constant).
        [$employee] = $this->resolveEmployeePublicly($email);

        // Anti-énumération : même réponse que l'email existe ou non.
        if ($employee !== null) {
            $token = Str::random(64);

            DB::table('public.password_reset_tokens')->insert([
                'email' => $email,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Mail::to($email)->send(new PasswordResetMail($token, $email));
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'PASSWORD_RESET_SENT',
            'localized_message' => __('auth.password_reset_sent'),
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
                'message' => __('auth.invalid_reset_token'),
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        // #4495 : résolution via la ligne de jeton (company_id + employee_id) —
        // O(1), pas de dépendance au balayage multi-schéma. Fallback lookup
        // public pour les jetons legacy/test sans company_id/employee_id.
        if (isset($row->company_id, $row->employee_id)) {
            [$employee, $employeeSchema] = $this->resolveEmployeeForToken($row);
        } else {
            [$employee, $employeeSchema] = $this->resolveEmployeePublicly($email);
        }

        if ($employee === null) {
            return new JsonResponse([
                'message' => __('auth.invalid_reset_token'),
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        // Consommation ATOMIQUE du jeton (issue #3944) : l'UPDATE conditionnel
        // (`used_at IS NULL` + non expiré) est la seule source de vérité —
        // deux requêtes concurrentes avec le même jeton ne peuvent pas
        // consommer le même enregistrement (l'une obtient 0 ligne affectée).
        $consumed = DB::table('public.password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        if ($consumed === 0) {
            // Perdu la course (déjà consommé par une requête concurrente)
            // ou expiré entre le check et l'update → refus générique.
            return new JsonResponse([
                'message' => __('auth.invalid_reset_token'),
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

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
            'success' => true,
            'message' => 'PASSWORD_RESET_DONE',
            'localized_message' => __('auth.password_reset_done'),
        ]);
    }

    /**
     * Résolution PUBLIQUE d'un employé par email — #4495.
     *
     * Chemin non authentifié (POST /auth/forgot-password) : user_lookups
     * uniquement, jamais de balayage multi-schéma. Un email absent du lookup
     * est traité « aucun compte » (réponse uniforme + temps ≈ constant) —
     * l'ancien fallback `findEmployeeInTenantSchemas()` créait un oracle de
     * timing (N SET search_path + SELECT par tenant pour un email inconnu).
     *
     * @return array{0: Employee|null, 1: string|null} — [employé, schéma tenant éventuel]
     */
    private function resolveEmployeePublicly(string $email): array
    {
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;

        try {
            $lookup = null;
            if ($this->lookupTableExists()) {
                $lookup = DB::table($this->lookupTable())
                    ->where('email', $email)
                    ->first();
            }

            if (! $lookup) {
                return [null, null];
            }

            $lookupSchema = is_string($lookup->schema_name ?? null) ? $lookup->schema_name : null;

            if ($lookupSchema !== null && $this->isSafeSchemaName($lookupSchema)) {
                $this->setTenantSearchPath($lookupSchema);
            }

            // #2652 : schéma tenant absent/migré partiel ⇒ « aucun employé ».
            if ($lookupSchema !== null && ! $this->tenantEmployeesTableExists($lookupSchema)) {
                return [null, null];
            }

            /** @var Employee|null $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $lookup->company_id)
                ->where('id', $lookup->employee_id)
                ->where('email', $email)
                ->first();

            return [$employee, $lookupSchema];
        } catch (QueryException $e) {
            // Jamais de 500 sur la résolution (schéma absent, table partielle).
            Log::warning('auth.password_reset_public_resolution_failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return [null, null];
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }
    }

    /**
     * Résolution authentifiée par le jeton — #4495.
     *
     * Chemin `reset()` : la ligne `public.password_reset_tokens` porte
     * company_id + employee_id → on charge l'employé depuis SON schéma tenant
     * (via public.companies.schema_name) en O(1), sans dépendre du lookup ni
     * du balayage multi-schéma.
     *
     * @return array{0: Employee|null, 1: string|null} — [employé, schéma tenant éventuel]
     */
    private function resolveEmployeeForToken(stdClass $tokenRow): array
    {
        $previousSearchPath = DB::getDriverName() === 'pgsql' ? $this->currentSearchPath() : null;

        try {
            $schema = DB::table('public.companies')
                ->where('id', $tokenRow->company_id)
                ->value('schema_name');

            $schemaName = is_string($schema) && $schema !== '' ? $schema : null;

            if ($schemaName !== null && $this->isSafeSchemaName($schemaName)) {
                $this->setTenantSearchPath($schemaName);
            }

            if ($schemaName !== null && ! $this->tenantEmployeesTableExists($schemaName)) {
                return [null, null];
            }

            /** @var Employee|null $employee */
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $tokenRow->company_id)
                ->where('id', $tokenRow->employee_id)
                ->first();

            return [$employee, $schemaName];
        } catch (QueryException $e) {
            Log::warning('auth.password_reset_token_resolution_failed', [
                'company_id' => $tokenRow->company_id,
                'message' => $e->getMessage(),
            ]);

            return [null, null];
        } finally {
            if ($previousSearchPath !== null && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }
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
