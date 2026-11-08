<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Actions\ListPlatformUsersAction;
use App\Modules\Platform\Application\Actions\SetPlatformUserActiveAction;
use App\Modules\Platform\Application\Actions\ShowPlatformUserAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des utilisateurs plateforme (issue #2269).
 *
 * Décision #2519 (review 2026-08-15) : depuis le merge de #2466, le CRUD SPA
 * super-admin passe par /platform/users (PlatformUserController). Cet endpoint
 * /admin/users est CONSERVÉ comme source du lien employé (company.employee_id)
 * nécessaire à la surface d'impersonation PA2-ADM-006 (#2518) — aucune autre
 * API ne l'expose. À supprimer uniquement si l'impersonation est abandonnée.
 *
 * Contrat :
 *   - GET   /api/v1/admin/users          → liste paginée (recherche, tri, filtre statut)
 *   - GET   /api/v1/admin/users/{user}   → détail + entreprise liée
 *   - PATCH /api/v1/admin/users/{user}   → {is_active: bool} (422 si auto-désactivation)
 *
 * Contrôleur mince : les lectures/écritures ciblent le schéma PUBLIC
 * (`public.users`, `public.user_employee_links`, `public.companies`) — ce
 * contrôleur ne passe pas par le middleware tenant et force
 * `search_path TO public` comme PlatformCompanyLookup (pattern #1952/#1873).
 * La logique métier vit dans les Actions List/Show/SetPlatformUserActive
 * (issue #6569, audit DDD M1).
 */
class PlatformUsersController extends Controller
{
    /** Colonnes de tri autorisées (jamais de colonne arbitraire). */
    private const SORTABLE = ['created_at', 'last_login_at', 'email', 'last_name'];

    public function __construct(
        private readonly ListPlatformUsersAction $list,
        private readonly ShowPlatformUserAction $show,
        private readonly SetPlatformUserActiveAction $setActive,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sortBy = in_array($request->string('sort_by')->toString(), self::SORTABLE, true)
            ? $request->string('sort_by')->toString()
            : 'created_at';
        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        $result = $this->list->execute($perPage, $search, $status, $sortBy, $sortDir);

        $data = collect($result['rows'])
            ->map(fn ($row): array => $this->formatUser($row))
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => $result['meta'],
        ]);
    }

    public function show(Request $request, int $user): JsonResponse
    {
        $row = $this->show->execute($user);

        if ($row === null) {
            abort(404, 'USER_NOT_FOUND');
        }

        return response()->json(['data' => $this->formatUser($row, withRoles: true)]);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var \App\Core\Tenant\Domain\Models\SuperAdmin|null $actor */
        $actor = $request->user();
        $result = $this->setActive->execute(
            $user,
            (bool) $validated['is_active'],
            $actor?->email,
        );

        if ($result['status'] === 'not_found') {
            abort(404, 'USER_NOT_FOUND');
        }

        if ($result['status'] === 'self_disable') {
            return response()->json([
                'error' => 'SELF_DISABLE_FORBIDDEN',
                'message' => __('errors.SELF_DISABLE_FORBIDDEN'),
            ], 422);
        }

        /** @var \stdClass $row */
        $row = $result['row'];

        return response()->json(['data' => $this->formatUser($row, withRoles: true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(\stdClass $row, bool $withRoles = false): array
    {
        $user = [
            'id' => (int) $row->id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'email' => $row->email,
            'phone' => $row->phone,
            'status' => $row->status,
            'is_active' => $row->status === 'active',
            'preferred_language' => $row->preferred_language,
            'last_login_at' => $row->last_login_at,
            'failed_login_attempts' => (int) $row->failed_login_attempts,
            'locked_until' => $row->locked_until,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'company' => $row->company ?? null,
        ];

        if ($withRoles) {
            $user['roles'] = $row->roles ?? [];
        }

        return $user;
    }
}
