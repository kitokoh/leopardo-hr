<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Exceptions\DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #7475 — Suppression sûre d'un tenant (parcours en deux temps).
 *
 * POURQUOI CE SERVICE EXISTE
 * --------------------------
 * Supprimer un tenant n'est pas un `DELETE` : une société porte un schéma
 * tenant (employés, contrats, paie, pointages, documents, invitations) **et**
 * des lignes plateforme (abonnement, factures, audit). L'issue #7475 demande
 * explicitement un parcours **en deux temps** — désactivation, puis purge
 * explicite — avec inventaire chiffré, confirmation forte et journalisation.
 *
 * DÉCISIONS D'IMPLÉMENTATION
 * --------------------------
 * 1. **Le périmètre tenant est découvert, pas listé en dur.** Tous les tenants
 *    vivent dans un schéma unique `shared_tenants` (le mode « un schéma par
 *    tenant » est verrouillé, cf. `Company::booted()`) : il n'y a donc rien à
 *    `DROP`, seulement des lignes à supprimer par `company_id`. La liste des
 *    tables est lue dans `information_schema` (cf. `TenantDeletionInventory`),
 *    ce qui évite qu'une table ajoutée demain échappe à la purge.
 * 2. **La suppression est vérifiée, pas supposée.** Après le balayage, le
 *    service recompte les lignes du tenant : s'il en reste, la transaction est
 *    annulée et l'opération échoue avec la liste des tables fautives. Une purge
 *    partielle silencieuse serait pire qu'un refus.
 * 3. **Ordre géré par reprises successives.** Les dépendances `ON DELETE`
 *    varient d'une table à l'autre ; plutôt que de figer un ordre fragile, le
 *    balayage repasse sur les tables dont la suppression est refusée par une
 *    contrainte de clé étrangère, jusqu'à épuisement.
 * 4. **Deux modes, jamais implicites quand la paie est concernée.**
 *    `purge` = effacement complet (RGPD art. 17). `anonymize` = les pièces de
 *    paie sont **conservées** (obligation de conservation) et les identités
 *    sont effacées. Le mode `purge` n'est appliqué d'office que si le tenant
 *    n'a **aucune** donnée de paie (critère d'acceptation 5).
 * 5. **La preuve survit aux données.** Le compte rendu est écrit dans
 *    `public.tenant_deletion_audits` — table plateforme, hors du périmètre
 *    effacé. L'`audit_logs` du tenant disparaît avec le reste ; le journal
 *    d'audit du tenant n'est pas une preuve opposable de sa propre suppression.
 */
final class TenantDeletionService
{
    public const MODE_PURGE = 'purge';

    public const MODE_ANONYMIZE = 'anonymize';

    /**
     * Un tenant n'est supprimable qu'après désactivation (critère 1).
     *
     * @var list<string>
     */
    public const DELETABLE_STATUSES = ['suspended', 'expired'];

    /**
     * Lignes **plateforme** rattachées au tenant, supprimées avec lui.
     *
     * Liste volontairement fermée : le périmètre `public` mêle des données
     * opérationnelles du tenant (invitations, provisionings, tickets support)
     * et des données de **pilotage plateforme** (pistes marketing, commissions
     * partenaires). Seules les premières sont effacées. Le test
     * `PlatformCompanyDeletionApiTest::test_public_tenant_scope_is_fully_classified`
     * échoue si une nouvelle table `public` portant `company_id` n'est classée
     * ni ici, ni dans les FK à neutraliser, ni dans les tables conservées.
     *
     * @var list<string>
     */
    public const PUBLIC_TENANT_TABLES = [
        'user_invitations',
        'user_employee_links',
        'trial_provisionings',
        'company_sso_configs',
        'platform_announcement_companies',
        'platform_impersonation_sessions',
        'platform_support_tickets',
        'feature_flag_audits',
        // `user_lookups` est créée par une migration **publique** mais porte un
        // `company_id` avec `ON DELETE CASCADE` : c'est un référentiel du tenant,
        // pas une donnée de pilotage plateforme.
        'user_lookups',
        // Idem : jetons de réinitialisation (colonne `company_id` ajoutée par
        // #5540) et table de résolution des canaux webhook CRM — deux
        // référentiels du tenant créés côté `public`.
        'password_reset_tokens',
        'crm_webhook_channel_lookup',
    ];

    /**
     * Tables `public` portant `company_id` et **conservées** : ce sont des
     * données de pilotage commercial ou de rémunération partenaire, pas des
     * données du tenant. Elles sont documentées ici pour que la garde de
     * classification du test reste exhaustive.
     *
     * `commissions` / `partner_referrals` portent `ON DELETE CASCADE` vers
     * `companies` : en mode `purge` la suppression de la société les emporte
     * mécaniquement (comportement du schéma, pas de ce service).
     *
     * @var list<string>
     */
    public const PUBLIC_RETAINED_TABLES = [
        'marketing_leads',
        'partners',
        'partner_referrals',
        'commissions',
        'public_holidays',
        'crm_webhook_endpoints',
        'company_requests',
    ];

    /**
     * FK `public` à neutraliser **avant** de supprimer la société, sans quoi
     * PostgreSQL refuse le `DELETE` (`user_employee_links.company_id` est
     * `NOT NULL` sans cascade, `company_requests.approved_company_id` est
     * nullable sans cascade).
     *
     * @var list<array{table: string, column: string, strategy: string}>
     */
    public const PUBLIC_FK_STRATEGIES = [
        ['table' => 'user_employee_links', 'column' => 'company_id', 'strategy' => 'delete'],
        ['table' => 'company_requests', 'column' => 'approved_company_id', 'strategy' => 'null'],
    ];

    /**
     * Colonnes d'identité effacées en mode `anonymize` (appliquées seulement
     * si la colonne existe : le schéma `employees` a bougé 30 fois).
     *
     * @var list<string>
     */
    private const ANONYMIZED_EMPLOYEE_COLUMNS = [
        'phone',
        'date_of_birth',
        'gender',
        'nationality',
        'national_id',
        'iban',
        'bank_account',
        'photo_path',
        'email_verified_at',
        'last_login_at',
        'extra_data',
        'metadata',
        'address_line',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'badge_number',
        'zkteco_id',
    ];

    private const MAX_SWEEP_PASSES = 25;

    public function __construct(
        private readonly TenantDeletionInventory $inventory,
    ) {}

    /**
     * Purge (ou anonymise) un tenant désactivé.
     *
     * @return array{
     *     company_id: string,
     *     mode: string,
     *     status: string,
     *     inventory: array<string, mixed>,
     *     deleted_counts: array<string, int>,
     *     audit_id: int
     * }
     */
    public function destroy(
        Company $company,
        string $mode,
        SuperAdmin $actor,
        ?string $requestId = null,
        ?string $reason = null,
    ): array {
        $inventory = $this->inventory->for($company);

        // #7475 (reliquat) — un tenant historique en schéma DÉDIÉ
        // (`tenancy_type = 'schema'`) porte ses données hors de
        // `shared_tenants` : le balayage ne supprimerait rien et l'opération se
        // déclarerait « terminée » à tort (silence dangereux, #7535). On refuse
        // explicitement, et la tentative est auditée.
        if ((string) $company->tenancy_type === 'schema') {
            $this->recordAudit($company, $mode, 'refused', $inventory, [], $actor, $requestId, 'TENANT_DELETION_UNSUPPORTED_TENANCY', $reason);

            throw new DomainException(
                'Dedicated-schema tenants are not supported by the deletion flow yet.',
                409,
                'TENANT_DELETION_UNSUPPORTED_TENANCY'
            );
        }

        if (! $this->inventory->isDeletable($company)) {
            $this->recordAudit($company, $mode, 'refused', $inventory, [], $actor, $requestId, 'TENANT_NOT_DEACTIVATED', $reason);

            throw new DomainException(
                'Tenant must be deactivated before deletion.',
                409,
                'TENANT_NOT_DEACTIVATED'
            );
        }

        if ($inventory['has_payroll_data'] && ! in_array($mode, [self::MODE_PURGE, self::MODE_ANONYMIZE], true)) {
            $this->recordAudit($company, $mode, 'refused', $inventory, [], $actor, $requestId, 'TENANT_DELETION_MODE_REQUIRED', $reason);

            throw new DomainException(
                'Tenant holds payroll data: an explicit deletion mode is required.',
                422,
                'TENANT_DELETION_MODE_REQUIRED'
            );
        }

        if (! in_array($mode, [self::MODE_PURGE, self::MODE_ANONYMIZE], true)) {
            throw new DomainException('Unknown deletion mode.', 422, 'TENANT_DELETION_MODE_INVALID');
        }

        $deleted = [];

        try {
            DB::transaction(function () use ($company, $mode, &$deleted): void {
                $deleted = $this->sweepTenantData($company, $mode);

                if ($mode === self::MODE_ANONYMIZE) {
                    $this->anonymizeEmployees($company);
                }

                $deleted = $this->mergeCounts($deleted, $this->sweepPlatformRows($company->id));

                $this->applyPublicFkStrategies($company->id);

                if ($mode === self::MODE_ANONYMIZE) {
                    $this->anonymizeCompany($company);
                } else {
                    $company->delete();
                }
            });
        } catch (Throwable $exception) {
            $this->recordAudit($company, $mode, 'failed', $inventory, $deleted, $actor, $requestId, $exception->getMessage(), $reason);

            Log::error('platform.tenant_deletion.failed', [
                'company_id' => $company->id,
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Vérification hors transaction : en mode `purge` la société n'existe
        // plus, le comptage doit donc être indépendant de la ligne supprimée.
        if ($mode === self::MODE_PURGE) {
            $remaining = $this->inventory->tenantRowCount($company->id);

            if ($remaining > 0) {
                throw new DomainException(
                    'Tenant purge left '.$remaining.' row(s) behind.',
                    500,
                    'TENANT_DELETION_INCOMPLETE'
                );
            }
        }

        $auditId = $this->recordAudit($company, $mode, 'completed', $inventory, $deleted, $actor, $requestId, null, $reason);

        return [
            'company_id' => $company->id,
            'mode' => $mode,
            'status' => 'completed',
            'inventory' => $inventory,
            'deleted_counts' => $deleted,
            'audit_id' => $auditId,
        ];
    }

    /**
     * Dernières opérations de suppression connues pour un tenant (critère 4 :
     * « le résultat est consultable »).
     *
     * @return list<array<string, mixed>>
     */
    public function history(string $companyId, int $limit = 20): array
    {
        DB::statement('SET search_path TO public');

        /** @var list<array<string, mixed>> $rows */
        $rows = DB::table('public.tenant_deletion_audits')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(static function (object $row): array {
                /** @var array<string, mixed> $decoded */
                $decoded = [
                    'id' => (int) $row->id,
                    'mode' => (string) $row->mode,
                    'status' => (string) $row->status,
                    'inventory' => json_decode((string) $row->inventory, true) ?: [],
                    'actor_email' => $row->actor_email !== null ? (string) $row->actor_email : null,
                    'reason' => $row->reason !== null ? (string) $row->reason : null,
                    'failure_reason' => $row->failure_reason !== null ? (string) $row->failure_reason : null,
                    'created_at' => (string) $row->created_at,
                ];

                return $decoded;
            })
            ->values()
            ->all();

        return $rows;
    }

    /**
     * #7576 — Piste d'audit **plateforme** : la même preuve que {@see history()},
     * mais non scopée à une entreprise vivante.
     *
     * `history()` a besoin d'une société existante pour être appelée (la route
     * passe par `PlatformCompanyLookup::findOrFail`), or une suppression réussie
     * la fait disparaître : l'opérateur qui doit répondre à « qui a supprimé cet
     * espace, quand, pourquoi, et avec quel volume » n'avait plus aucun lecteur.
     * La ligne d'audit, elle, survit dans `public.tenant_deletion_audits` et
     * porte le nom et le slug de l'espace disparu.
     *
     * Aucune donnée locataire n'est exposée : la ligne ne contient que des
     * compteurs, des identités plateforme (`actor_email`) et la justification.
     *
     * @return list<array<string, mixed>>
     */
    public function platformHistory(?string $companyId = null, ?string $slug = null, int $limit = 20): array
    {
        DB::statement('SET search_path TO public');

        $query = DB::table('public.tenant_deletion_audits')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($companyId !== null && $companyId !== '') {
            $query->where('company_id', $companyId);
        }

        if ($slug !== null && $slug !== '') {
            $query->where('company_slug', $slug);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->get()
            ->map(static function (object $row): array {
                /** @var array<string, mixed> $decoded */
                $decoded = [
                    'id' => (int) $row->id,
                    'company_id' => (string) $row->company_id,
                    'company_name' => (string) $row->company_name,
                    'company_slug' => $row->company_slug !== null ? (string) $row->company_slug : null,
                    'mode' => (string) $row->mode,
                    'status' => (string) $row->status,
                    'inventory' => json_decode((string) $row->inventory, true) ?: [],
                    'deleted_counts' => json_decode((string) $row->deleted_counts, true) ?: [],
                    'actor_user_id' => $row->actor_user_id !== null ? (int) $row->actor_user_id : null,
                    'actor_email' => $row->actor_email !== null ? (string) $row->actor_email : null,
                    'reason' => $row->reason !== null ? (string) $row->reason : null,
                    'failure_reason' => $row->failure_reason !== null ? (string) $row->failure_reason : null,
                    'request_id' => $row->request_id !== null ? (string) $row->request_id : null,
                    'created_at' => (string) $row->created_at,
                ];

                return $decoded;
            })
            ->values()
            ->all();

        return $rows;
    }

    /**
     * Supprime les lignes du tenant dans le schéma `shared_tenants`.
     *
     * En mode `anonymize`, les tables de conservation paie sont exclues du
     * balayage (elles ne sont pas effacées, elles sont conservées).
     *
     * @return array<string, int>
     */
    private function sweepTenantData(Company $company, string $mode): array
    {
        $retained = $mode === self::MODE_ANONYMIZE ? TenantDeletionInventory::PAYROLL_RETENTION_TABLES : [];

        $pending = array_values(array_diff($this->inventory->tenantTables(), $retained));
        $deleted = [];

        for ($pass = 0; $pass < self::MAX_SWEEP_PASSES && $pending !== []; $pass++) {
            $progress = false;
            $retry = [];

            foreach ($pending as $table) {
                try {
                    // #7475 (reliquat) — SAVEPOINT par table. En PostgreSQL, un
                    // `DELETE` refusé par une contrainte FK avorte la
                    // transaction EN COURS (`SQLSTATE 25P02`) : la boucle de
                    // reprise ci-dessous devenait inopérante (toute instruction
                    // suivante échouait, jusqu'à l'écriture de l'audit
                    // d'échec). Un `DB::transaction()` imbriqué émet
                    // SAVEPOINT / ROLLBACK TO SAVEPOINT : l'échec d'une table
                    // n'empoisonne plus les suivantes.
                    $count = DB::transaction(
                        fn (): int => DB::table(TenantDeletionInventory::qualified($table))
                            ->where('company_id', $company->id)
                            ->delete(),
                        1,
                    );

                    if ($count > 0) {
                        $deleted[$table] = ($deleted[$table] ?? 0) + $count;
                        $progress = true;
                    }
                } catch (QueryException $exception) {
                    // Contrainte de clé étrangère : une table enfant n'a pas
                    // encore été vidée. On repasse après elle.
                    $retry[] = $table;
                }
            }

            if (! $progress && $retry !== []) {
                // Aucune table n'a progressé alors que des suppressions sont
                // refusées : les tables restantes sont bloquées par des
                // dépendances hors périmètre. On s'arrête et on le dit.
                $this->assertNoBlockedTables($company->id, $retry);
            }

            $pending = $retry;
        }

        return $deleted;
    }

    /**
     * @param  list<string>  $tables
     */
    private function assertNoBlockedTables(string $companyId, array $tables): void
    {
        $blocked = [];

        foreach ($tables as $table) {
            if (DB::table(TenantDeletionInventory::qualified($table))->where('company_id', $companyId)->exists()) {
                $blocked[] = $table;
            }
        }

        if ($blocked !== []) {
            throw new DomainException(
                'Tenant data could not be fully deleted (blocking tables: '.implode(', ', $blocked).').',
                500,
                'TENANT_DELETION_BLOCKED'
            );
        }
    }

    /**
     * Efface les identités des employés conservés (mode `anonymize`).
     *
     * `employees.email` est unique **globalement** : l'e-mail anonymisé doit
     * donc rester unique par ligne — d'où la dérivation depuis l'`id`.
     */
    private function anonymizeEmployees(Company $company): void
    {
        $update = [
            'first_name' => 'Anonymise',
            'last_name' => 'Anonymise',
            'status' => 'archived',
            'email' => DB::raw("'anonymized+' || id::text || '@invalid.local'"),
            'password_hash' => '',
        ];

        foreach (self::ANONYMIZED_EMPLOYEE_COLUMNS as $column) {
            $profile = TenantDeletionInventory::tenantColumnProfile('employees', $column);

            if ($profile === null) {
                continue;
            }

            if ($profile['nullable']) {
                $update[$column] = null;

                continue;
            }

            // Colonne NOT NULL : on écrit une valeur neutre du bon type plutôt
            // que de laisser la donnée personnelle en place (`employees.metadata`
            // est `jsonb NOT NULL` — le test l'a révélé).
            if ($profile['type'] === 'jsonb' || $profile['type'] === 'json') {
                $update[$column] = DB::raw("'{}'::jsonb");

                continue;
            }

            if (str_contains($profile['type'], 'character') || $profile['type'] === 'text') {
                $update[$column] = '';
            }
        }

        DB::table(TenantDeletionInventory::qualified('employees'))
            ->where('company_id', $company->id)
            ->update($update);
    }

    /**
     * Supprime les lignes plateforme rattachées au tenant.
     *
     * @return array<string, int>
     */
    private function sweepPlatformRows(string $companyId): array
    {
        $deleted = [];

        foreach (self::PUBLIC_TENANT_TABLES as $table) {
            if (! $this->publicTableExists($table)) {
                continue;
            }

            $count = DB::table($table)->where('company_id', $companyId)->delete();

            if ($count > 0) {
                $deleted[$table] = $count;
            }
        }

        return $deleted;
    }

    /**
     * Neutralise les FK `public` sans cascade avant le `DELETE` de la société.
     */
    private function applyPublicFkStrategies(string $companyId): void
    {
        foreach (self::PUBLIC_FK_STRATEGIES as $strategy) {
            if (! $this->publicTableExists($strategy['table']) || ! $this->publicColumnExists($strategy['table'], $strategy['column'])) {
                continue;
            }

            if ($strategy['strategy'] === 'delete') {
                DB::table($strategy['table'])->where($strategy['column'], $companyId)->delete();

                continue;
            }

            DB::table($strategy['table'])->where($strategy['column'], $companyId)->update([$strategy['column'] => null]);
        }
    }

    /**
     * Anonymise la société elle-même (mode `anonymize`).
     *
     * La ligne est **conservée** : sans elle, les pièces de paie conservées ne
     * seraient plus rattachables à un employeur, donc plus opposables.
     */
    private function anonymizeCompany(Company $company): void
    {
        $metadata = $company->metadata ?? [];
        $metadata['anonymized_at'] = now()->toIso8601String();

        // `public.companies.email` et `city` sont NOT NULL (migration
        // `2026_04_01_000002_create_companies_table.php`) : on écrit une valeur
        // neutre plutôt que `null` — sinon la purge échoue sur une contrainte
        // (constaté au test). L'e-mail dérivé de l'identifiant reste unique.
        $company->forceFill([
            'name' => 'Espace anonymise ('.$company->id.')',
            'email' => 'anonymized+'.$company->id.'@invalid.local',
            'phone' => null,
            'address' => null,
            'city' => '',
            'notes' => null,
            'status' => 'expired',
            'metadata' => $metadata,
        ])->save();
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $extra
     * @return array<string, int>
     */
    private function mergeCounts(array $counts, array $extra): array
    {
        foreach ($extra as $key => $value) {
            $counts[$key] = ($counts[$key] ?? 0) + $value;
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $inventory
     * @param  array<string, int>  $deleted
     */
    private function recordAudit(
        Company $company,
        string $mode,
        string $status,
        array $inventory,
        array $deleted,
        SuperAdmin $actor,
        ?string $requestId,
        ?string $failureReason,
        ?string $reason = null,
    ): int {
        DB::statement('SET search_path TO public');

        return (int) DB::table('public.tenant_deletion_audits')->insertGetId([
            'company_id' => $company->id,
            'company_name' => (string) ($inventory['company_name'] ?? $company->name),
            'company_slug' => $company->slug,
            'mode' => $mode,
            'status' => $status,
            'inventory' => json_encode($inventory),
            'deleted_counts' => json_encode($deleted),
            'reason' => $reason,
            'actor_user_id' => $actor->getAuthIdentifier(),
            'actor_email' => $actor->email,
            'request_id' => $requestId,
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }

    private function publicTableExists(string $table): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            ['public', $table]
        ) !== null;
    }

    private function publicColumnExists(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            ['public', $table, $column]
        ) !== null;
    }
}
