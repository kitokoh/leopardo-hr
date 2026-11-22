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
 * #9764 — RÉSOLUTION DE L'ÉTAPE. Deux schémas coexistent dans
 * `crm_opportunities` : `stage` (varchar, écrit par `ConvertLeadAction` et lu
 * par le kanban) et `stage_id` (bigint, ajouté par #7452 mais JAMAIS renseigné
 * par le code applicatif). Le read model joignait en INTERNE sur
 * `s.id = o.stage_id` : la jointure ne ramenait donc aucune ligne et le
 * dashboard affichait toujours zéro, alors que le kanban affichait les
 * opportunités réelles.
 *
 * L'étape effective est désormais résolue par `effectiveStage()` :
 *  - `stage_id` renseigné ET pointant une étape du MÊME tenant → cette étape
 *    (nom, position, is_won/is_lost) ;
 *  - sinon → la valeur `stage` (whitelist `CrmOpportunityStage`), avec
 *    `won`/`lost` comme états terminaux et la position de l'énumération.
 *
 * La jointure est un LEFT JOIN : une opportunité sans `stage_id` reste
 * comptée. C'est ce qui rend le dashboard et le kanban cohérents.
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
        $stageAggregates = $this->effectiveStage($companyId)
            ->where('eff.is_won', false)
            ->where('eff.is_lost', false)
            ->select(
                'eff.stage_id',
                'eff.stage_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(eff.amount), 0) as value')
            )
            ->groupBy('eff.stage_id', 'eff.stage_name', 'eff.stage_position')
            ->orderBy('eff.stage_position')
            ->orderBy('eff.stage_name')
            ->get();

        // Totaux par statut dérivé (open/won/lost).
        $totals = $this->effectiveStage($companyId)
            ->select(
                DB::raw('COUNT(*) FILTER (WHERE NOT eff.is_won AND NOT eff.is_lost) as open_count'),
                DB::raw('COALESCE(SUM(eff.amount) FILTER (WHERE NOT eff.is_won AND NOT eff.is_lost), 0) as open_value'),
                DB::raw('COUNT(*) FILTER (WHERE eff.is_won) as won_count'),
                DB::raw('COUNT(*) FILTER (WHERE eff.is_lost) as lost_count')
            )
            ->first();

        // Stagnation : opportunités ouvertes dont le compte n'a eu aucune
        // activité depuis 30 jours (crm_activities.occurred_at).
        $stagnant = $this->effectiveStage($companyId)
            ->leftJoin(
                DB::raw('(SELECT account_id, MAX(occurred_at) AS last_activity FROM crm_activities WHERE company_id = '.DB::getPdo()->quote($companyId).' GROUP BY account_id) a'),
                'a.account_id',
                '=',
                'eff.account_id'
            )
            ->where('eff.is_won', false)
            ->where('eff.is_lost', false)
            ->whereRaw('(a.last_activity IS NULL OR a.last_activity < NOW() - INTERVAL \'30 days\')')
            ->count();

        // Owners (accounts/opportunités) sans opportunité ouverte.
        $allOwners = collect()
            ->concat($this->distinctOwners('crm_accounts', $companyId))
            ->concat($this->distinctOwners('crm_opportunities', $companyId))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ownersWithOpen = $this->effectiveStage($companyId)
            ->where('eff.is_won', false)
            ->where('eff.is_lost', false)
            ->whereNotNull('eff.owner_id')
            ->distinct()
            ->pluck('eff.owner_id')
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
                'stage_id' => $row->stage_id === null ? null : (int) $row->stage_id,
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
     * #9764 — Vue « étape effective » des opportunités du tenant, exposée sous
     * l'alias `eff` (sous-requête : les colonnes calculées ne sont pas
     * référençables directement dans le FROM).
     */
    private function effectiveStage(string $companyId): \Illuminate\Database\Query\Builder
    {
        return DB::query()->fromSub($this->effectiveStageSubquery($companyId), 'eff');
    }

    /**
     * #9764 — Sous-requête « étape effective ».
     *
     * LEFT JOIN sur `crm_pipeline_stages` (jamais INNER : une opportunité sans
     * `stage_id` doit rester comptée) + repli sur la colonne `stage`.
     *
     * Colonnes exposées : `id`, `amount`, `owner_id`, `account_id`,
     * `stage_id`, `stage_name`, `stage_position`, `is_won`, `is_lost`.
     */
    private function effectiveStageSubquery(string $companyId): \Illuminate\Database\Query\Builder
    {
        $hasStageId = Schema::hasColumn('crm_opportunities', 'stage_id');

        $query = DB::table('crm_opportunities as o')
            ->where('o.company_id', $companyId)
            ->whereNull('o.deleted_at');

        if (! $hasStageId) {
            // Base antérieure à #7452 : la colonne n'existe pas, tout passe
            // par `stage`. On garde le même contrat de colonnes.
            return $query->select([
                'o.id',
                'o.amount',
                'o.owner_id',
                'o.account_id',
                DB::raw('NULL::bigint as stage_id'),
                DB::raw('COALESCE(o.stage, \'prospecting\') as stage_name'),
                DB::raw($this->fallbackPositionSql('o.stage').' as stage_position'),
                DB::raw('(o.stage = \'won\') as is_won'),
                DB::raw('(o.stage = \'lost\') as is_lost'),
            ]);
        }

        return $query
            ->leftJoin('crm_pipeline_stages as s', function ($join): void {
                $join->on('s.id', '=', 'o.stage_id')
                    // Anti-cross-tenant : une étape d'un autre tenant ne peut
                    // jamais qualifier l'opportunité.
                    ->on('s.company_id', '=', 'o.company_id');
            })
            ->select([
                'o.id',
                'o.amount',
                'o.owner_id',
                'o.account_id',
                's.id as stage_id',
                DB::raw('COALESCE(s.name, COALESCE(o.stage, \'prospecting\')) as stage_name'),
                DB::raw('COALESCE(s.position, '.$this->fallbackPositionSql('o.stage').') as stage_position'),
                DB::raw('CASE WHEN s.id IS NOT NULL THEN s.is_won ELSE (o.stage = \'won\') END as is_won'),
                DB::raw('CASE WHEN s.id IS NOT NULL THEN s.is_lost ELSE (o.stage = \'lost\') END as is_lost'),
            ]);
    }

    /**
     * Position de repli d'une étape, dérivée de l'ordre de l'énumération
     * `CrmOpportunityStage` (prospecting → lost).
     */
    private function fallbackPositionSql(string $column): string
    {
        $cases = [];

        foreach (\App\Modules\CRM\Domain\Enums\CrmOpportunityStage::cases() as $index => $case) {
            $cases[] = sprintf("WHEN '%s' THEN %d", $case->value, $index);
        }

        return 'CASE '.$column.' '.implode(' ', $cases).' ELSE 99 END';
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
        // #7452 — sans le « AND » en tête : whereRaw l'ajoute déjà (sinon
        // « where … and AND … » → 42601 syntax error).
        $archivedClause = Schema::hasColumn('crm_accounts', 'archived_at')
            ? 'a.archived_at IS NULL'
            : 'a.status <> \'archived\'';

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
        //
        // #5713/#5714 — `crm_contacts.email` est casté `encrypted` : la valeur
        // stockée est un payload chiffré, DIFFÉRENT à chaque écriture (IV
        // aléatoire). Un `GROUP BY LOWER(TRIM(email))` côté SQL ne peut donc
        // jamais regrouper deux lignes, et l'estimation valait toujours 0.
        // On regroupe sur la valeur déchiffrée par le cast du modèle ; le
        // volume reste borné par le filtre `company_id` (scan tenant).
        // Note : `CrmContact` est reference en pleine qualification pour ne pas
        // ajouter de ligne `use` avant DB/Schema — layer-purity-allowlist.txt
        // epingle ces deux facades aux lignes 7-8 et le fichier est immuable
        // (#5584). `fully_qualified_strict_types` est desactive dans pint.json.
        $duplicates = \App\Modules\CRM\Domain\Models\CrmContact::query()
            ->where('company_id', $companyId)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->pluck('email')
            ->map(static fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter(static fn (string $email): bool => $email !== '')
            ->countBy()
            ->filter(static fn (int $count): bool => $count > 1)
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
