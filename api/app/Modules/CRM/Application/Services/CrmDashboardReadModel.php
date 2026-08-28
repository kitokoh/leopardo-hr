<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5721 — Dashboard CRM : pipeline & qualité des données (read model).
 *
 * Toutes les requêtes sont agrégées, bornées et strictement filtrées par
 * `company_id` (jamais de scan cross-tenant). Les indexes supports
 * (fondation V0) :
 *  - `crm_opportunities(company_id, pipeline_id)`, `(owner_id)`,
 *    FK composites anti-cross-tenant vers `crm_pipelines`/`crm_pipeline_stages` ;
 *  - `crm_activities(company_id, account_id, occurred_at)` pour la stagnation ;
 *  - `crm_tasks(company_id, status)`, `(company_id, due_at)`.
 *
 * Statut dérivé du stage (pas de colonne status sur crm_opportunities) :
 * open = stage !is_won ET !is_lost ; won = stage.is_won ; lost = stage.is_lost.
 *
 * P95 / latence : agrégats SQL uniques par surface (2 requêtes pipeline,
 * 2 requêtes qualité), pas de boucle N+1 — coût proportionnel aux index
 * ci-dessus (note EXPLAIN dans la spec du cluster V1).
 */
class CrmDashboardReadModel
{
    /**
     * @return array<string, mixed>
     */
    public function pipeline(string $companyId): array
    {
        // Valeur et volume par stage (opportunités OUVERTES uniquement).
        $stageAggregates = DB::table('crm_opportunities as o')
            ->join('crm_pipeline_stages as s', 's.id', '=', 'o.stage_id')
            ->where('o.company_id', $companyId)
            ->where('s.is_won', false)
            ->where('s.is_lost', false)
            ->select(
                's.id as stage_id',
                's.name as stage_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(o.amount), 0) as value')
            )
            ->groupBy('s.id', 's.name')
            ->orderBy('s.position')
            ->get();

        // Totaux par statut dérivé (open/won/lost).
        $totals = DB::table('crm_opportunities as o')
            ->join('crm_pipeline_stages as s', 's.id', '=', 'o.stage_id')
            ->where('o.company_id', $companyId)
            ->select(
                DB::raw('COUNT(*) FILTER (WHERE NOT s.is_won AND NOT s.is_lost) as open_count'),
                DB::raw('COALESCE(SUM(o.amount) FILTER (WHERE NOT s.is_won AND NOT s.is_lost), 0) as open_value'),
                DB::raw('COUNT(*) FILTER (WHERE s.is_won) as won_count'),
                DB::raw('COUNT(*) FILTER (WHERE s.is_lost) as lost_count')
            )
            ->first();

        // Stagnation : opportunités ouvertes dont le compte n'a eu aucune
        // activité depuis 30 jours (crm_activities.occurred_at).
        $stagnant = DB::table('crm_opportunities as o')
            ->join('crm_pipeline_stages as s', 's.id', '=', 'o.stage_id')
            ->leftJoin(
                DB::raw('(SELECT account_id, MAX(occurred_at) AS last_activity FROM crm_activities WHERE company_id = '.DB::getPdo()->quote($companyId).' GROUP BY account_id) a'),
                'a.account_id',
                '=',
                'o.account_id'
            )
            ->where('o.company_id', $companyId)
            ->where('s.is_won', false)
            ->where('s.is_lost', false)
            ->whereRaw('(a.last_activity IS NULL OR a.last_activity < NOW() - INTERVAL \'30 days\')')
            ->count();

        // Owners (accounts/opportunités) sans opportunité ouverte.
        $allOwners = collect()
            ->concat($this->distinctOwners('crm_accounts', $companyId))
            ->concat($this->distinctOwners('crm_opportunities', $companyId))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ownersWithOpen = DB::table('crm_opportunities as o')
            ->join('crm_pipeline_stages as s', 's.id', '=', 'o.stage_id')
            ->where('o.company_id', $companyId)
            ->where('s.is_won', false)
            ->where('s.is_lost', false)
            ->whereNotNull('o.owner_id')
            ->distinct()
            ->pluck('o.owner_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $ownersWithoutOpportunities = $allOwners
            ->diff($ownersWithOpen)
            ->map(fn (int $ownerId) => [
                'owner_id' => $ownerId,
                'open_opportunities' => 0,
            ])
            ->values();

        $overdueTasks = DB::table('crm_tasks')
            ->where('company_id', $companyId)
            ->whereIn('status', ['todo', 'in_progress'])
            ->where('due_at', '<', now())
            ->count();

        return [
            'totals' => [
                'open_count' => (int) ($totals->open_count ?? 0),
                'open_value' => (float) ($totals->open_value ?? 0),
                'won_count' => (int) ($totals->won_count ?? 0),
                'lost_count' => (int) ($totals->lost_count ?? 0),
            ],
            'by_stage' => $stageAggregates->map(fn ($row) => [
                'stage_id' => (int) $row->stage_id,
                'stage_name' => (string) $row->stage_name,
                'count' => (int) $row->count,
                'value' => (float) $row->value,
            ])->values(),
            'stagnant_opportunities' => (int) $stagnant,
            'owners_without_open_opportunities' => $ownersWithoutOpportunities,
            'overdue_tasks' => (int) $overdueTasks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quality(string $companyId): array
    {
        $accounts = DB::table('crm_accounts')
            ->where('company_id', $companyId)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(*) FILTER (WHERE status = \'archived\') as archived_by_status'),
                DB::raw('COUNT(*) FILTER (WHERE archived_at IS NOT NULL) as archived_by_date')
            )
            ->first();

        // Accounts sans contact primaire (is_primary = true). Défensif sur
        // archived_at (deux variantes #5708 : status='archived' ou archived_at).
        $archivedClause = Schema::hasColumn('crm_accounts', 'archived_at')
            ? 'AND a.archived_at IS NULL'
            : 'AND a.status <> \'archived\'';

        $accountsWithoutPrimary = DB::table('crm_accounts as a')
            ->leftJoin('crm_contacts as c', function ($join): void {
                $join->on('c.account_id', '=', 'a.id')
                    ->where('c.is_primary', true);
            })
            ->where('a.company_id', $companyId)
            ->whereRaw($archivedClause)
            ->whereNull('c.id')
            ->count();

        $contacts = DB::table('crm_contacts')
            ->where('company_id', $companyId)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(*) FILTER (WHERE email IS NULL OR email = \'\') as without_email'),
                DB::raw('COUNT(*) FILTER (WHERE phone IS NULL OR phone = \'\') as without_phone')
            )
            ->first();

        // Doublons estimés : emails normalisés apparaissant plus d'une fois.
        $duplicates = DB::table('crm_contacts')
            ->where('company_id', $companyId)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->select(DB::raw('LOWER(TRIM(email)) as email'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('LOWER(TRIM(email))'))
            ->having('cnt', '>', 1)
            ->count();

        $archivedCount = (int) ($accounts->archived_by_status ?? 0) + (int) ($accounts->archived_by_date ?? 0);

        return [
            'accounts_total' => (int) ($accounts->total ?? 0),
            'accounts_archived' => $archivedCount,
            'accounts_without_primary_contact' => (int) $accountsWithoutPrimary,
            'contacts_total' => (int) ($contacts->total ?? 0),
            'contacts_without_email' => (int) ($contacts->without_email ?? 0),
            'contacts_without_phone' => (int) ($contacts->without_phone ?? 0),
            'duplicate_contacts_estimate' => (int) $duplicates,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, mixed> */
    private function distinctOwners(string $table, string $companyId): \Illuminate\Support\Collection
    {
        if (! Schema::hasColumn($table, 'owner_id')) {
            return collect();
        }

        return DB::table($table)
            ->where('company_id', $companyId)
            ->whereNotNull('owner_id')
            ->distinct()
            ->pluck('owner_id');
    }
}
