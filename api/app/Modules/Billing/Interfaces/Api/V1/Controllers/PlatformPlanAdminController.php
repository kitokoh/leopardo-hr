<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #7430 (BC-21 BILLING) — une offre tarifaire est PARAMÉTRABLE, pas seedée.
 *
 * Constat du propriétaire : « la partie souscription où on est censé être capable
 * de paramétrer tout ce qui est lié à nos offres… c'est du paramétrage ». Or la
 * table `plans` n'était alimentée que par `PlanSeeder` et l'admin n'exposait
 * qu'une LECTURE (`GET /platform/plans`, `PlatformPlanController`) : changer un
 * prix, une limite d'employés ou la matrice de features exigeait un déploiement.
 *
 * Ce contrôleur ajoute le CRUD réel, avec trois garde-fous produit :
 *   1. **Une offre utilisée ne se supprime pas** — elle s'archive (`is_active`
 *      = false). Le `DELETE` répond 409 et nomme le nombre de tenants concernés
 *      (le code de plan est aussi référencé par `subscriptions.plan`).
 *   2. **Le duplicata n'est jamais publié** — il naît archivé, à l'admin de
 *      l'ouvrir (une copie qui apparaîtrait aussitôt dans le tunnel de
 *      souscription serait un piège).
 *   3. **Chaque modification est auditée** (`AuditLog`, société nulle : c'est
 *      une décision plateforme), comme les bascules d'activation de module.
 *
 * Réservé au super-admin : le groupe de routes porte `auth:super_admin_api`
 * (jamais exposé à l'espace tenant, garde MAT-003/#5861).
 */
class PlatformPlanAdminController extends Controller
{
    /** Bornes de sécurité : une offre publique ne peut pas être absurde. */
    private const MAX_PRICE = 9_999_999.99;

    private const MAX_EMPLOYEES = 1_000_000;

    private const MAX_TRIAL_DAYS = 365;

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $name = (string) $validated['name'];
        $this->assertNameIsFree($name);

        $id = DB::table('plans')->insertGetId([
            'name' => $name,
            'price_monthly' => $this->payload($validated, 'price_monthly', 0),
            'price_yearly' => $this->payload($validated, 'price_yearly', 0),
            'max_employees' => $this->nullableInt($validated, 'max_employees'),
            'features' => json_encode($this->features($validated), JSON_THROW_ON_ERROR),
            'trial_days' => $this->payload($validated, 'trial_days', 14),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $plan = $this->findOrFail($id);
        $this->audit($request, 'plan.created', $id, [], $this->present($plan));

        return new JsonResponse(['data' => $this->present($plan)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $before = $this->findOrFail($id);
        $validated = $this->validated($request, partial: true);

        if (array_key_exists('name', $validated)) {
            $name = (string) $validated['name'];
            $this->assertNameIsFree($name, exceptId: $id);
        }

        $changes = [];

        foreach (['price_monthly', 'price_yearly', 'trial_days'] as $column) {
            if (array_key_exists($column, $validated)) {
                $changes[$column] = $validated[$column];
            }
        }

        if (array_key_exists('max_employees', $validated)) {
            $changes['max_employees'] = $this->nullableInt($validated, 'max_employees');
        }

        if (array_key_exists('is_active', $validated)) {
            $changes['is_active'] = (bool) $validated['is_active'];
        }

        if (array_key_exists('name', $validated)) {
            $changes['name'] = (string) $validated['name'];
        }

        if (array_key_exists('features', $validated)) {
            $changes['features'] = json_encode($this->features($validated), JSON_THROW_ON_ERROR);
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'plan' => [__('errors.PLAN_NOTHING_TO_UPDATE')],
            ]);
        }

        DB::table('plans')->where('id', $id)->update($changes);

        $after = $this->findOrFail($id);
        $this->audit($request, 'plan.updated', $id, $this->present($before), $this->present($after));

        return new JsonResponse(['data' => $this->present($after)]);
    }

    /**
     * Duplique une offre : mêmes prix, limites et matrice de features, mais
     * **archivée d'office** (jamais publiée par accident) et nommée de façon
     * unique.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = $this->findOrFail($id);
        $presented = $this->present($source);

        $newId = DB::table('plans')->insertGetId([
            'name' => $this->uniqueCopyName((string) $source->name),
            'price_monthly' => $source->price_monthly,
            'price_yearly' => $source->price_yearly,
            'max_employees' => $source->max_employees,
            'features' => json_encode($presented['features'], JSON_THROW_ON_ERROR),
            'trial_days' => $source->trial_days,
            'is_active' => false,
        ]);

        $copy = $this->findOrFail($newId);
        $this->audit($request, 'plan.duplicated', $newId, [], $this->present($copy));

        return new JsonResponse(['data' => $this->present($copy)], 201);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $before = $this->findOrFail($id);

        DB::table('plans')->where('id', $id)->update(['is_active' => false]);

        $after = $this->findOrFail($id);
        $this->audit($request, 'plan.archived', $id, $this->present($before), $this->present($after));

        return new JsonResponse(['data' => $this->present($after)]);
    }

    /**
     * Suppression **refusée** dès que l'offre est utilisée : un tenant porte des
     * données et des droits dérivés de son offre. Elle s'archive.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $plan = $this->findOrFail($id);
        $code = strtolower((string) $plan->name);

        $companies = DB::table('companies')->where('plan_id', $id)->count();
        $subscriptions = DB::table('subscriptions')->where('plan', $code)->count();

        if ($companies > 0 || $subscriptions > 0) {
            return new JsonResponse([
                'message' => __('errors.PLAN_IN_USE', ['count' => max($companies, $subscriptions)]),
                'errors' => [
                    'plan' => [__('errors.PLAN_IN_USE', ['count' => max($companies, $subscriptions)])],
                ],
                'data' => [
                    'plan_id' => $id,
                    'companies' => $companies,
                    'subscriptions' => $subscriptions,
                ],
            ], 409);
        }

        DB::table('plans')->where('id', $id)->delete();
        $this->audit($request, 'plan.deleted', $id, $this->present($plan), []);

        return new JsonResponse(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:50'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0', 'max:'.self::MAX_PRICE],
            'price_yearly' => ['sometimes', 'numeric', 'min:0', 'max:'.self::MAX_PRICE],
            'max_employees' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_EMPLOYEES],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:'.self::MAX_TRIAL_DAYS],
            'features' => ['sometimes', 'array'],
            'features.*' => ['boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * #7430 — un nom d'offre est unique côté plateforme : la contrainte est
     * vérifiée AVANT l'écriture pour répondre 422 plutôt qu'une 500 SQL. Le
     * docblock précédent décrivait un `$validated` qui n'existe pas dans cette
     * signature (relevé par PHPStan, `@param` sur paramètre inconnu).
     *
     * @param  int|null  $exceptId  Offre à exclure du contrôle (l'offre qu'on renomme).
     */
    private function assertNameIsFree(string $name, ?int $exceptId = null): void
    {
        $query = DB::table('plans')->where('name', $name);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => [__('errors.PLAN_NAME_TAKEN', ['name' => $name])],
            ]);
        }
    }

    private function uniqueCopyName(string $sourceName): string
    {
        $base = mb_substr($sourceName.' (copie)', 0, 43);
        $candidate = $base;
        $suffix = 2;

        while (DB::table('plans')->where('name', $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 46).' '.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function findOrFail(int $id): object
    {
        $plan = DB::table('plans')->where('id', $id)->first();

        abort_if($plan === null, 404, __('errors.PLAN_NOT_FOUND'));

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, bool>
     */
    private function features(array $validated): array
    {
        /** @var array<string, mixed> $raw */
        $raw = is_array($validated['features'] ?? null) ? $validated['features'] : [];

        $features = [];

        foreach ($raw as $key => $value) {
            $features[(string) $key] = (bool) $value;
        }

        return $features;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function payload(array $validated, string $key, int|float $default): int|float
    {
        $value = $validated[$key] ?? $default;

        return is_numeric($value) ? $value + 0 : $default;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableInt(array $validated, string $key): ?int
    {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return (int) $validated[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'price_monthly' => (float) $plan->price_monthly,
            'price_yearly' => (float) $plan->price_yearly,
            'max_employees' => $plan->max_employees !== null ? (int) $plan->max_employees : null,
            'features' => $this->decodeFeatures($plan->features ?? null),
            'trial_days' => (int) $plan->trial_days,
            'is_active' => (bool) $plan->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return $features;
        }

        if (! is_string($features) || $features === '') {
            return [];
        }

        $decoded = json_decode($features, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Request $request, string $action, int $planId, array $before, array $after): void
    {
        $actorId = $request->user()?->getAuthIdentifier();

        AuditLog::create([
            // Décision PLATEFORME : aucune société n'est concernée (colonne
            // nullable, cf. audit_logs) — l'entité auditée est l'offre elle-même.
            'company_id' => null,
            'user_id' => $actorId !== null ? (int) $actorId : null,
            'action' => $action,
            'module' => 'billing',
            'auditable_type' => 'plan',
            'auditable_id' => $planId,
            'old_values' => $before,
            'new_values' => $after,
        ]);
    }
}
