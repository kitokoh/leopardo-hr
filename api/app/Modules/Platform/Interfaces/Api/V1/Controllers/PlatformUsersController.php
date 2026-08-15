<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
 * Les lectures ciblent le schéma PUBLIC (`public.users`, `public.user_employee_links`,
 * `public.companies`) : ce contrôleur ne passe pas par le middleware tenant et
 * force `search_path TO public` comme PlatformCompanyLookup (pattern #1952/#1873).
 */
class PlatformUsersController extends Controller
{
    /** Colonnes de tri autorisées (jamais de colonne arbitraire). */
    private const SORTABLE = ['created_at', 'last_login_at', 'email', 'last_name'];

    /** Schéma tenant partagé (mode de déploiement par défaut de la plateforme). */
    private const TENANT_SCHEMA = 'shared_tenants';

    public function index(Request $request): JsonResponse
    {
        DB::statement('SET search_path TO public');

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sortBy = in_array($request->string('sort_by')->toString(), self::SORTABLE, true)
            ? $request->string('sort_by')->toString()
            : 'created_at';
        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('users as u')
            ->select([
                'u.id',
                'u.first_name',
                'u.last_name',
                'u.email',
                'u.phone',
                'u.status',
                'u.preferred_language',
                'u.last_login_at',
                'u.failed_login_attempts',
                'u.locked_until',
                'u.created_at',
                'u.updated_at',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where('u.first_name', 'ilike', $like)
                    ->orWhere('u.last_name', 'ilike', $like)
                    ->orWhere('u.email', 'ilike', $like);
            });
        }

        if (in_array($status, ['active', 'disabled', 'pending', 'suspended'], true)) {
            $query->where('u.status', $status);
        }

        $query->orderBy('u.'.$sortBy, $sortDir)->orderBy('u.id', 'desc');

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass> $paginator */
        $paginator = $query->paginate($perPage);

        $rows = collect($paginator->items());
        $this->enrichWithCompanies($rows);

        $data = $rows->map(fn ($row) => $this->formatUser($row))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $user): JsonResponse
    {
        DB::statement('SET search_path TO public');

        $row = DB::table('users')->where('id', $user)->first();

        if ($row === null) {
            abort(404, 'USER_NOT_FOUND');
        }

        $rows = collect([$row]);
        $this->enrichWithCompanies($rows);

        /** @var \stdClass $enriched */
        $enriched = $rows->first();
        return response()->json(['data' => $this->formatUser($enriched, withRoles: true)]);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        DB::statement('SET search_path TO public');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $row = DB::table('users')->where('id', $user)->first();

        if ($row === null) {
            abort(404, 'USER_NOT_FOUND');
        }

        $isActive = (bool) $validated['is_active'];

        // Garde auto-désactivation : le super-admin courant ne peut pas
        // désactiver un compte partageant son email (se tirerait la porte).
        /** @var SuperAdmin|null $actor */
        $actor = $request->user();
        if (
            ! $isActive
            && $actor !== null
            && strcasecmp((string) $actor->email, (string) $row->email) === 0
        ) {
            return response()->json([
                'error' => 'SELF_DISABLE_FORBIDDEN',
                'message' => __('errors.SELF_DISABLE_FORBIDDEN'),
            ], 422);
        }

        DB::table('users')
            ->where('id', $user)
            ->update(['status' => $isActive ? 'active' : 'disabled']);

        /** @var \stdClass $fresh */
        $fresh = DB::table('users')->where('id', $user)->first();

        $rows = collect([$fresh]);
        $this->enrichWithCompanies($rows);

        /** @var \stdClass $enriched */
        $enriched = $rows->first();
        return response()->json(['data' => $this->formatUser($enriched, withRoles: true)]);
    }

    /**
     * Enrichit une collection de lignes `users` avec l'entreprise liée
     * (lien actif le plus récent) — jamais d'info d'un autre tenant.
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $rows
     */
    private function enrichWithCompanies(\Illuminate\Support\Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $userIds = $rows->pluck('id')->all();

        $links = DB::table('user_employee_links')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('id')
            ->get()
            ->keyBy('user_id');

        $companyIds = $links->pluck('company_id')->unique()->filter()->all();

        $companies = $companyIds === []
            ? collect()
            : DB::table('companies')->whereIn('id', $companyIds)->get()->keyBy('id');

        foreach ($rows as $row) {
            $link = $links->get($row->id);
            $row->company = $link !== null
                ? [
                    'id' => $link->company_id,
                    'name' => $companies->get($link->company_id)?->name,
                    'link_status' => $link->status,
                    'employee_id' => $link->employee_id,
                ]
                : null;
        }
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
            $roles = DB::table('user_employee_links as l')
                ->join(self::TENANT_SCHEMA.'.employees as e', 'e.id', '=', 'l.employee_id')
                ->where('l.user_id', $row->id)
                ->select(['e.role', 'e.manager_role', 'l.company_id', 'l.status as link_status'])
                ->get()
                ->map(fn ($r) => [
                    'role' => $r->role,
                    'manager_role' => $r->manager_role,
                    'company_id' => $r->company_id,
                    'link_status' => $r->link_status,
                ]);

            $user['roles'] = $roles;
        }

        return $user;
    }
}
