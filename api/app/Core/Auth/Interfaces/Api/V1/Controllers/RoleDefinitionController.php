<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Enums\DelegablePermission;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\RoleDelegationGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * IAM-L5 (#9095) — rôles personnalisés de tenant.
 *
 * ## Le contrat
 *
 * Un `principal` compose un rôle nommé (« Caissier », « Chef de rang ») à
 * partir du registre **fermé** `DelegablePermission`, et l'attribue à un
 * collaborateur. Le rôle est lu **après** les enums : les gates existants
 * (`api.manager`, `EmployeeResource::capabilities()`) ne sont pas réécrits.
 *
 * ## La sécurité
 *
 * `permissions ⊆ capabilities(créateur)` à la création **et** à la
 * modification (`RoleDelegationGuard`). Un non-principal ne peut pas accorder
 * `delegation.manage` — il ne le détient pas, donc le plafond le refuse.
 * Un rôle de verticale est borné à sa verticale.
 *
 * ## Ce que ce contrôleur ne fait pas
 *
 * Aucune introduction de Spatie, aucune réécriture de gate, aucune permission
 * inventée : le registre est dérivé de `EmployeeResource::capabilities()`.
 */
class RoleDefinitionController extends Controller
{
    public function __construct(
        private readonly RoleDelegationGuard $guard,
    ) {}

    /**
     * GET /api/v1/company/roles — les rôles personnalisés ACTIFS du tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $actor instanceof Employee) {
            return response()->json(['message' => __('errors.UNAUTHENTICATED')], 401);
        }

        $rows = DB::table('role_assignments')
            ->where('company_id', $actor->company_id)
            ->whereNull('revoked_at')
            ->orderBy('name')
            ->get();

        $data = $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'permissions' => $this->decodePermissions($row->permissions),
            'vertical' => $row->vertical !== null ? (string) $row->vertical : null,
        ])->all();

        return response()->json([
            'data' => $data,
            'catalogue' => DelegablePermission::keys(),
        ]);
    }

    /**
     * POST /api/v1/company/roles — créer un rôle personnalisé.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $actor instanceof Employee) {
            return response()->json(['message' => __('errors.UNAUTHENTICATED')], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(DelegablePermission::keys())],
            'vertical' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var array{permissions: list<string>, name: string, vertical?: string|null} $validated */
        /** @var list<string> $permissions */
        $permissions = array_values(array_unique($validated['permissions']));

        // ORDRE VOLONTAIRE : le plafond d'abord, le droit de gérer ensuite.
        // Un `rh` qui tente d'accorder `is_principal` ou `delegation.manage`
        // doit recevoir le refus INFORMATIF (422 + la liste des permissions en
        // excès), pas un 403 générique : c'est ce que verrouille le scénario
        // #11 de la matrice IAM.
        $excess = $this->guard->excessFor($actor, $permissions);

        if ($excess !== []) {
            return response()->json([
                'error' => 'DELEGATION_EXCEEDS_ACTOR',
                'message' => __('errors.DELEGATION_EXCEEDS_ACTOR'),
                'excess' => $excess,
            ], 422);
        }

        if (! $this->guard->canManageDelegation($actor)) {
            return response()->json([
                'error' => 'DELEGATION_FORBIDDEN',
                'message' => __('errors.DELEGATION_FORBIDDEN'),
            ], 403);
        }

        $exists = DB::table('role_assignments')
            ->where('company_id', $actor->company_id)
            ->where('name', $validated['name'])
            ->whereNull('revoked_at')
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'ROLE_NAME_TAKEN',
                'message' => __('errors.ROLE_NAME_TAKEN'),
            ], 422);
        }

        $id = DB::table('role_assignments')->insertGetId([
            'company_id' => $actor->company_id,
            'name' => $validated['name'],
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'vertical' => $validated['vertical'] ?? null,
            'created_by_employee_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => (int) $id,
                'name' => $validated['name'],
                'permissions' => $permissions,
                'vertical' => $validated['vertical'] ?? null,
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/company/roles/{role} — modifier un rôle personnalisé.
     *
     * Le plafond s'applique **aussi** ici : une modification ne peut pas
     * élargir un rôle au-delà de ce que l'acteur détient.
     */
    public function update(Request $request, int $role): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $actor instanceof Employee) {
            return response()->json(['message' => __('errors.UNAUTHENTICATED')], 401);
        }

        if (! $this->guard->canManageDelegation($actor)) {
            return response()->json([
                'error' => 'DELEGATION_FORBIDDEN',
                'message' => __('errors.DELEGATION_FORBIDDEN'),
            ], 403);
        }

        $row = DB::table('role_assignments')
            ->where('id', $role)
            ->where('company_id', $actor->company_id)
            ->whereNull('revoked_at')
            ->first();

        if ($row === null) {
            return response()->json(['message' => __('errors.NOT_FOUND')], 404);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(DelegablePermission::keys())],
        ]);

        /** @var array{permissions: list<string>} $validated */
        /** @var list<string> $permissions */
        $permissions = array_values(array_unique($validated['permissions']));

        $excess = $this->guard->excessFor($actor, $permissions);

        if ($excess !== []) {
            return response()->json([
                'error' => 'DELEGATION_EXCEEDS_ACTOR',
                'message' => __('errors.DELEGATION_EXCEEDS_ACTOR'),
                'excess' => $excess,
            ], 422);
        }

        DB::table('role_assignments')
            ->where('id', $role)
            ->where('company_id', $actor->company_id)
            ->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        return response()->json([
            'data' => [
                'id' => (int) $role,
                'name' => (string) $row->name,
                'permissions' => $permissions,
                'vertical' => $row->vertical !== null ? (string) $row->vertical : null,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/company/roles/{role} — révoquer un rôle personnalisé.
     *
     * Révocation **effective à la requête suivante** : la ligne reste (audit du
     * geste) mais n'est plus lue par `index()` ni par le moteur d'enforcement.
     */
    public function revoke(Request $request, int $role): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $actor instanceof Employee) {
            return response()->json(['message' => __('errors.UNAUTHENTICATED')], 401);
        }

        if (! $this->guard->canManageDelegation($actor)) {
            return response()->json([
                'error' => 'DELEGATION_FORBIDDEN',
                'message' => __('errors.DELEGATION_FORBIDDEN'),
            ], 403);
        }

        $affected = DB::table('role_assignments')
            ->where('id', $role)
            ->where('company_id', $actor->company_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        if ($affected === 0) {
            return response()->json(['message' => __('errors.NOT_FOUND')], 404);
        }

        return response()->json(['data' => ['id' => (int) $role, 'revoked' => true]]);
    }

    private function actor(Request $request): ?Employee
    {
        $user = $request->user();

        return $user instanceof Employee ? $user : null;
    }

    /** @return list<string> */
    private function decodePermissions(mixed $raw): array
    {
        if (is_array($raw)) {
            /** @var list<string> $values */
            $values = array_values(array_filter($raw, 'is_string'));

            return $values;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var list<string> $values */
        $values = array_values(array_filter($decoded, 'is_string'));

        return $values;
    }
}
