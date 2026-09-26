<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire des utilisateurs plateforme (schéma PUBLIC) — accès données pour
 * la surface d'impersonation PA2-ADM-006 (#2518, /api/v1/admin/users).
 *
 * Les lectures ciblent `public.users`, `public.user_employee_links`,
 * `public.companies` et `shared_tenants.employees` (rôles) : ce service ne
 * passe pas par le middleware tenant et force `search_path TO public` comme
 * PlatformCompanyLookup (pattern #1952/#1873).
 *
 * Logique extraite de PlatformUsersController (issue #6569, audit DDD M1) —
 * les Actions Application délèguent ici (pattern Action Application → Service
 * Infrastructure, ADR-0020 ; ex. CompanyProvisioningService).
 */
final class PlatformUserDirectoryService
{
    /** Colonnes de tri autorisées (jamais de colonne arbitraire). */
    private const SORTABLE = ['created_at', 'last_login_at', 'email', 'last_name'];

    /** Schéma tenant partagé (mode de déploiement par défaut de la plateforme). */
    private const TENANT_SCHEMA = 'shared_tenants';

    /**
     * Liste paginée des utilisateurs plateforme.
     *
     * @return array{
     *   rows: array<int, \stdClass>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function list(int $perPage, string $search, string $status, string $sortBy, string $sortDir): array
    {
        DB::statement('SET search_path TO public');

        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

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

        return [
            'rows' => $rows->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Détail d'un utilisateur plateforme, enrichi (`->company`, `->roles`).
     */
    public function show(int $userId): ?\stdClass
    {
        DB::statement('SET search_path TO public');

        $row = DB::table('users')->where('id', $userId)->first();

        if ($row === null) {
            return null;
        }

        $rows = collect([$row]);
        $this->enrichWithCompanies($rows);

        /** @var \stdClass $enriched */
        $enriched = $rows->first();
        $enriched->roles = $this->rolesFor($enriched->id);

        return $enriched;
    }

    /**
     * Activation/désactivation d'un utilisateur plateforme.
     *
     * @return array{status: 'not_found'}|array{status: 'self_disable'}|array{status: 'updated', row: \stdClass}
     */
    public function setActive(int $userId, bool $isActive, ?string $actorEmail): array
    {
        DB::statement('SET search_path TO public');

        $row = DB::table('users')->where('id', $userId)->first();

        if ($row === null) {
            return ['status' => 'not_found'];
        }

        // Garde auto-désactivation : le super-admin courant ne peut pas
        // désactiver un compte partageant son email (se tirerait la porte).
        if (
            ! $isActive
            && $actorEmail !== null
            && strcasecmp($actorEmail, (string) $row->email) === 0
        ) {
            return ['status' => 'self_disable'];
        }

        DB::table('users')
            ->where('id', $userId)
            ->update(['status' => $isActive ? 'active' : 'disabled']);

        /** @var \stdClass $fresh */
        $fresh = DB::table('users')->where('id', $userId)->first();

        $rows = collect([$fresh]);
        $this->enrichWithCompanies($rows);

        /** @var \stdClass $enriched */
        $enriched = $rows->first();
        $enriched->roles = $this->rolesFor($enriched->id);

        return ['status' => 'updated', 'row' => $enriched];
    }

    /**
     * Enrichit une collection de lignes `users` avec l'entreprise liée
     * (lien actif le plus récent) — jamais d'info d'un autre tenant.
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $rows
     */
    private function enrichWithCompanies(Collection $rows): void
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
     * Rôles tenant d'un utilisateur.
     *
     * @return array<int, array{role: mixed, manager_role: mixed, company_id: mixed, link_status: mixed}>
     */
    private function rolesFor(int $userId): array
    {
        return DB::table('user_employee_links as l')
            ->join(self::TENANT_SCHEMA.'.employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.user_id', $userId)
            ->select(['e.role', 'e.manager_role', 'l.company_id', 'l.status as link_status'])
            ->get()
            ->map(fn ($r): array => [
                'role' => $r->role,
                'manager_role' => $r->manager_role,
                'company_id' => $r->company_id,
                'link_status' => $r->link_status,
            ])
            ->all();
    }
}
