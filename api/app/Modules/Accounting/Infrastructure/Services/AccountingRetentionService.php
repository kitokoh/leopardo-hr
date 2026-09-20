<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * #5273 / #7929 — Rétention légale des documents comptables.
 *
 * Seuls les documents FINALISÉS (paid, cancelled, overdue) sont éligibles à
 * la purge : un brouillon ou un document envoyé reste potentiellement en
 * évolution. Le cutoff court depuis `issue_date` (l'émission), pas la
 * création. Les lignes et paiements suivent le document (FK cascade #5221) ;
 * le PDF archivé est supprimé du storage avec le document.
 *
 * Issue #7929 : la durée de rétention est résolue par pays du tenant
 * (RETENTION_MONTHS_BY_COUNTRY, sources citées) ; l'override opérateur
 * ponctuel (`--older-than` sur accounting:purge-expired) reste prioritaire.
 */
class AccountingRetentionService
{
    /**
     * Durées de conservation légales par pays, en mois (issue #7929) —
     * sources vérifiées le 2026-09-20, détail dans
     * docs/security/ACCOUNTING_RETENTION.md :
     *   - zone OHADA (17 États — dont les membres CEMAC/CEDEAO du registre
     *     CountryDefaults) : 10 ans — AUDCIF (acte uniforme relatif au droit
     *     comptable et à l'information financière, 2017), art. 24 ;
     *   - FR : 10 ans — code de commerce, art. L123-22 ;
     *   - TR : 10 ans — Türk Ticaret Kanunu (TTK) n° 6102, m. 82 (le VUK
     *     m. 253 prévoit 5 ans ; la durée la plus longue est retenue) ;
     *   - CA : 6 ans — loi de l'impôt sur le revenu (LIR), par. 230(4) et
     *     règlement 5800 (6 ans après la fin de l'année d'imposition) ;
     *   - défaut conservateur : 120 mois (config accounting.retention_months).
     *
     * @var array<string, int>
     */
    public const RETENTION_MONTHS_BY_COUNTRY = [
        // OHADA — AUDCIF art. 24 (10 ans).
        'SN' => 120,
        'CI' => 120,
        'ML' => 120,
        'BF' => 120,
        'BJ' => 120,
        'TG' => 120,
        'NE' => 120,
        'CM' => 120,
        'GA' => 120,
        'CG' => 120,
        'TD' => 120,
        'CF' => 120,
        'GQ' => 120,
        // France — C. com. L123-22 (10 ans).
        'FR' => 120,
        // Turquie — TTK 6102 m. 82 (10 ans).
        'TR' => 120,
        // Canada — LIR 230(4) + règlement 5800 (6 ans).
        'CA' => 72,
    ];

    /**
     * @param  list<DocumentStatus>  $purgeableStatuses
     */
    public function __construct(
        private readonly array $purgeableStatuses = [DocumentStatus::Paid, DocumentStatus::Cancelled, DocumentStatus::Overdue],
    ) {}

    /**
     * Rétention (en mois) applicable à un pays (issue #7929) :
     *   1. durée légale du pays (RETENTION_MONTHS_BY_COUNTRY) ;
     *   2. défaut conservateur 120 mois pour un pays inconnu ou hors table
     *      (config accounting.retention_months, env
     *      ACCOUNTING_RETENTION_MONTHS) — jamais plus court sans base légale.
     * L'override opérateur ponctuel reste `--older-than` sur la commande de
     * purge (durée unique forcée, comportement historique conservé).
     */
    public function retentionMonthsFor(?string $country): int
    {
        $code = strtoupper(trim((string) $country));

        return self::RETENTION_MONTHS_BY_COUNTRY[$code]
            ?? max(1, (int) config('accounting.retention_months', 120));
    }

    /**
     * Nombre de documents éligibles à la purge (sans rien supprimer).
     */
    public function countEligible(int $months, Carbon $now = new Carbon): int
    {
        return $this->query($months, $now)->count();
    }

    /**
     * Purge les documents finalisés antérieurs au cutoff. Retourne les
     * documents supprimés (modèles) pour rapport.
     *
     * @return list<AccountingDocument>
     */
    public function purge(int $months, bool $dryRun = false, Carbon $now = new Carbon): array
    {
        return $this->delete($this->query($months, $now)->get()->all(), $dryRun);
    }

    /**
     * Purge avec rétention résolue par pays du tenant (issue #7929) :
     * chaque entreprise ayant des documents est purgée selon la durée de sa
     * juridiction (retentionMonthsFor). Retourne les documents supprimés.
     *
     * @return list<AccountingDocument>
     */
    public function purgeByCountry(bool $dryRun = false, Carbon $now = new Carbon): array
    {
        $companyIds = AccountingDocument::query()
            ->withoutGlobalScopes()
            ->distinct()
            ->pluck('company_id')
            ->all();

        if ($companyIds === []) {
            return [];
        }

        /** @var array<string, string|null> $countries */
        $countries = Company::query()
            ->whereIn('id', $companyIds)
            ->pluck('country', 'id')
            ->all();

        $purged = [];

        foreach ($companyIds as $companyId) {
            $months = $this->retentionMonthsFor($countries[$companyId] ?? null);

            $documents = $this->query($months, $now)
                ->where('company_id', $companyId)
                ->get()
                ->all();

            foreach ($this->delete($documents, $dryRun) as $document) {
                $purged[] = $document;
            }
        }

        return $purged;
    }

    /**
     * @param  array<int, AccountingDocument>  $documents
     * @return list<AccountingDocument>
     */
    private function delete(array $documents, bool $dryRun): array
    {
        foreach ($documents as $document) {
            if ($document->pdf_path !== null && $document->pdf_path !== '') {
                Storage::disk('local')->delete($document->pdf_path);
            }

            if (! $dryRun) {
                $document->delete(); // lignes + paiements en cascade
            }
        }

        return array_values($documents);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AccountingDocument>
     */
    private function query(int $months, Carbon $now)
    {
        $cutoff = $now->copy()->subMonths(max(1, $months))->toDateString();
        $statuses = array_map(static fn (DocumentStatus $status): string => $status->value, $this->purgeableStatuses);

        return AccountingDocument::query()
            ->withoutGlobalScopes()
            ->whereIn('status', $statuses)
            ->where('issue_date', '<', $cutoff);
    }
}
