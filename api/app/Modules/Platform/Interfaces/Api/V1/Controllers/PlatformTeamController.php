<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Shared\Rules\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Issue #7553 — équipe interne de la plateforme.
 *
 * Le propriétaire du SaaS (rôle `super_admin`) peut déléguer une partie de
 * l'administration à des collaborateurs internes (support, finance, ops,
 * marketing) au lieu de partager le compte omniscient.
 *
 * Route : `/api/v1/platform/team/*`, garde `auth:super_admin_api` +
 * `platform.permission:team.manage` (porté uniquement par `super_admin`).
 *
 * Garde-fous (non négociables, ils évitent de se verrouiller dehors) :
 *  - un compte ne peut pas modifier son propre rôle ni se désactiver ;
 *  - rétrograder ou désactiver le DERNIER super admin actif est refusé (422) ;
 *  - désactiver un compte révoque ses tokens Sanctum (parité #2630) ;
 *  - `platform_role` n'est jamais mass-assignable (#3597) — assignation
 *    explicite, et chaque mutation est auditée.
 */
class PlatformTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $members = SuperAdmin::query()
            ->orderBy('id')
            ->get();

        return new JsonResponse([
            'data' => $members
                ->map(fn (SuperAdmin $member): array => $this->serialize($member, $actor))
                ->values(),
            'meta' => [
                'total' => $members->count(),
                'active_super_admins' => $this->activeSuperAdminCount(),
                'roles' => PlatformRole::matrix(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('super_admins', 'email')],
            // #8021 — politique unique #5620 : min 12 + chiffre + blocklist.
            'password' => PasswordPolicy::required(confirmed: false),
            'platform_role' => ['required', Rule::in(PlatformRole::values())],
        ]);

        // #4695 : password_hash est NOT NULL en base et hors $fillable — un
        // create() naïf insérerait NULL et violerait la contrainte.
        $member = new SuperAdmin([
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
        ]);
        $member->forceFill(['password_hash' => Hash::make($validated['password'])])->save();
        // #3597 : status et platform_role ne sont pas mass-assignables.
        $member->status = 'active';
        $member->platform_role = $validated['platform_role'];
        $member->save();

        $this->audit($request, $member, 'platform_team_created', [
            'platform_role' => $member->platform_role,
        ]);

        return (new JsonResponse(['data' => $this->serialize($member->fresh() ?? $member, $this->actor($request))]))
            ->setStatusCode(201);
    }

    public function updateRole(Request $request, SuperAdmin $superAdmin): JsonResponse
    {
        $validated = $request->validate([
            'platform_role' => ['required', Rule::in(PlatformRole::values())],
        ]);

        $actor = $this->actor($request);
        $previousRole = $superAdmin->platformRole();

        if ($this->isSelf($actor, $superAdmin)) {
            return new JsonResponse([
                'error' => 'CANNOT_CHANGE_OWN_PLATFORM_ROLE',
                'message' => __('errors.CANNOT_CHANGE_OWN_PLATFORM_ROLE'),
            ], 422);
        }

        $target = PlatformRole::from($validated['platform_role']);

        if ($previousRole->isSuperAdmin() && ! $target->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            return new JsonResponse([
                'error' => 'LAST_SUPER_ADMIN_REQUIRED',
                'message' => __('errors.LAST_SUPER_ADMIN_REQUIRED'),
            ], 422);
        }

        // #3597 : assignation explicite (jamais mass-assignable).
        $superAdmin->platform_role = $target->value;
        $superAdmin->save();

        $this->audit($request, $superAdmin, 'platform_team_role_changed', [
            'platform_role' => $target->value,
            'previous_platform_role' => $previousRole->value,
        ]);

        return new JsonResponse(['data' => $this->serialize($superAdmin->fresh() ?? $superAdmin, $actor)]);
    }

    public function activate(Request $request, SuperAdmin $superAdmin): JsonResponse
    {
        $superAdmin->forceFill(['status' => 'active'])->save();

        $this->audit($request, $superAdmin, 'platform_team_activated');

        return new JsonResponse(['data' => $this->serialize($superAdmin->fresh() ?? $superAdmin, $this->actor($request))]);
    }

    public function deactivate(Request $request, SuperAdmin $superAdmin): JsonResponse
    {
        $actor = $this->actor($request);

        if ($this->isSelf($actor, $superAdmin)) {
            return new JsonResponse([
                'error' => 'CANNOT_DISABLE_OWN_ACCOUNT',
                'message' => __('errors.CANNOT_DISABLE_OWN_ACCOUNT'),
            ], 422);
        }

        if ($superAdmin->platformRole()->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            return new JsonResponse([
                'error' => 'LAST_SUPER_ADMIN_REQUIRED',
                'message' => __('errors.LAST_SUPER_ADMIN_REQUIRED'),
            ], 422);
        }

        $superAdmin->forceFill(['status' => 'deactivated'])->save();
        // Sécurité #2630 : un compte désactivé ne garde pas de token vivant.
        $superAdmin->tokens()->delete();

        $this->audit($request, $superAdmin, 'platform_team_deactivated');

        return new JsonResponse(['data' => $this->serialize($superAdmin->fresh() ?? $superAdmin, $actor)]);
    }

    /**
     * Le compte appelant, résolu sur la garde plateforme. Le middleware
     * `platform.permission` garantit déjà sa présence, mais un contrôleur ne
     * doit jamais dépendre implicitement de son middleware.
     */
    private function actor(Request $request): SuperAdmin
    {
        $actor = $request->user('super_admin_api');

        abort_unless($actor instanceof SuperAdmin, 403, 'PLATFORM_ACCOUNT_REQUIRED');

        return $actor;
    }

    private function isSelf(SuperAdmin $actor, SuperAdmin $target): bool
    {
        return (int) $actor->getAuthIdentifier() === (int) $target->getAuthIdentifier();
    }

    /**
     * Nombre de comptes `super_admin` encore ACTIFS. C'est la seule mesure qui
     * protège contre l'auto-verrouillage : un super admin suspendu ne peut
     * plus rouvrir la porte.
     */
    private function activeSuperAdminCount(): int
    {
        return SuperAdmin::query()
            ->whereIn('platform_role', [PlatformRole::SuperAdmin->value])
            ->where(function ($query): void {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SuperAdmin $member, SuperAdmin $actor): array
    {
        $role = $member->platformRole();

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'status' => $member->status ?? 'active',
            'platform_role' => $role->value,
            'platform_role_label' => $role->label(),
            'permissions' => $role->permissionValues(),
            'is_self' => $this->isSelf($actor, $member),
            'last_login_at' => $member->last_login_at?->toIso8601String(),
            'created_at' => $member->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function audit(Request $request, SuperAdmin $member, string $action, array $changes = []): void
    {
        try {
            AuditLog::query()->create([
                'company_id' => null,
                'user_id' => null,
                'action' => $action,
                'auditable_type' => SuperAdmin::class,
                'auditable_id' => $member->id,
                'old_values' => null,
                'new_values' => [
                    'email' => $member->email,
                    'status' => $member->status,
                    'platform_role' => $member->platform_role,
                    ...$changes,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'metadata' => [
                    'actor' => (string) ($request->user('super_admin_api')?->getAuthIdentifier() ?? 'system'),
                    'permission' => PlatformPermission::TeamManage->value,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
