<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Models\AccountingTaxRate;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion du référentiel de TVA du tenant (issue #9376).
 *
 * Remplace le taux unique `accounting_documents.tva_rate` par un référentiel
 * par tenant, seedé depuis les défauts pays légaux
 * (`AccountingSettingsDefaults::tvaRates()`).
 *
 * Invariants garantis :
 *   - un seul taux `is_default` actif par tenant (celui qui s'applique quand
 *     aucune ligne ne précise de taux) ;
 *   - un taux utilisé par une ligne de document ne peut pas être supprimé
 *     (archivage à la place) : l'historique des factures reste lisible ;
 *   - le code est unique par tenant et immuable après création.
 */
class TaxRateService
{
    /**
     * Provisionne le référentiel d'un tenant depuis ses défauts pays.
     * Idempotent : ne touche pas aux taux déjà existants (le comptable a pu
     * modifier les libellés ou désactiver un taux).
     *
     * @return int nombre de taux créés
     */
    public function seedForCompany(string $companyId, ?string $country, ?string $province = null): int
    {
        $defaults = AccountingSettingsDefaults::tvaRates($country, $province);
        $created = 0;

        foreach ($defaults as $index => $definition) {
            $kind = $this->kindFromLabelKey((string) ($definition['label_key'] ?? 'standard'));

            $exists = AccountingTaxRate::query()
                ->where('company_id', $companyId)
                ->where('code', $this->codeFor($kind, (float) $definition['rate']))
                ->exists();

            if ($exists) {
                continue;
            }

            AccountingTaxRate::query()->create([
                'company_id' => $companyId,
                'code' => $this->codeFor($kind, (float) $definition['rate']),
                'label' => (string) $definition['label'],
                'rate' => (float) $definition['rate'],
                'country' => $country !== null ? strtoupper($country) : null,
                'kind' => $kind,
                'is_default' => $index === 0,
                'is_active' => true,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Taux applicable par défaut (le seul `is_default` actif). `null` si le
     * tenant n'a pas de référentiel — l'appelant doit alors fournir un taux
     * explicite, ou retomber sur `accounting_settings.tva_rate`.
     */
    public function defaultRateFor(string $companyId): ?AccountingTaxRate
    {
        return AccountingTaxRate::query()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Référentiel complet résolu par code — sert au calcul des documents.
     *
     * @return array<string, float>
     */
    public function rateMapFor(string $companyId): array
    {
        return AccountingTaxRate::query()
            ->where('company_id', $companyId)
            ->pluck('rate', 'code')
            ->map(static fn ($rate): float => (float) $rate)
            ->all();
    }

    /**
     * Référentiel résolu par code → identifiant, pour que la ligne de document
     * référence le taux qu'elle a réellement appliqué.
     *
     * @return array<string, int>
     */
    public function idMapFor(string $companyId): array
    {
        return AccountingTaxRate::query()
            ->where('company_id', $companyId)
            ->pluck('id', 'code')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Crée ou met à jour un taux. Le code n'est jamais modifiable : un changement
     * de taux légal se traduit par un nouveau code, pour que l'historique des
     * factures reste interprétable.
     *
     * @param  array{code: string, label: string, rate: float, kind?: string, is_default?: bool, is_active?: bool}  $payload
     */
    public function upsert(string $companyId, array $payload): AccountingTaxRate
    {
        $rate = (float) $payload['rate'];
        $kind = $this->kindFromLabelKey((string) ($payload['kind'] ?? 'standard'));

        DB::transaction(function () use ($companyId, $payload, $rate, $kind): void {
            if (($payload['is_default'] ?? false) === true) {
                // Un seul défaut par tenant.
                AccountingTaxRate::query()
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        /** @var AccountingTaxRate $taxRate */
        $taxRate = AccountingTaxRate::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => $payload['code']],
            [
                'label' => $payload['label'],
                'rate' => $rate,
                'kind' => $kind,
                'is_default' => (bool) ($payload['is_default'] ?? false),
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ],
        );

        return $taxRate;
    }

    /**
     * Supprime un taux. Refusé (409 côté contrôleur) s'il est utilisé par une
     * ligne de document : l'historique ne doit pas devenir muet.
     */
    public function delete(string $companyId, AccountingTaxRate $taxRate): bool
    {
        if ($this->isUsed((int) $taxRate->id)) {
            return false;
        }

        $taxRate->delete();

        return true;
    }

    public function isUsed(int $taxRateId): bool
    {
        return DB::table('accounting_document_lines')
            ->where('tax_rate_id', $taxRateId)
            ->exists();
    }

    /**
     * `standard`/`reduced`/`super_reduced` selon le libellé pays, avec repli
     * explicite sur `standard` (un libellé inconnu n'est pas une erreur : la
     * classification sert au classement, pas au calcul).
     */
    private function kindFromLabelKey(string $labelKey): string
    {
        return match ($labelKey) {
            'standard', 'gst' => AccountingTaxRate::KIND_STANDARD,
            'reduced', 'hst', 'pst', 'qst', 'rst' => AccountingTaxRate::KIND_REDUCED,
            'super_reduced' => AccountingTaxRate::KIND_SUPER_REDUCED,
            'zero', 'sales_tax' => AccountingTaxRate::KIND_ZERO,
            'exempt' => AccountingTaxRate::KIND_EXEMPT,
            default => AccountingTaxRate::KIND_STANDARD,
        };
    }

    /**
     * Code lisible et stable : `TVA-STD-19`, `TVA-RED-9`, `TVA-SUP-1`.
     * Le taux étant dans le code, un changement légal crée naturellement un
     * nouveau code plutôt que de réécrire l'ancien.
     */
    private function codeFor(string $kind, float $rate): string
    {
        $prefix = match ($kind) {
            AccountingTaxRate::KIND_REDUCED => 'TVA-RED',
            AccountingTaxRate::KIND_SUPER_REDUCED => 'TVA-SUP',
            AccountingTaxRate::KIND_ZERO => 'TVA-ZRO',
            AccountingTaxRate::KIND_EXEMPT => 'TVA-EXO',
            default => 'TVA-STD',
        };

        // 9.975 → 9.975, 19.0 → 19 (pas de « 19.0000 » dans un code).
        $normalized = rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');

        return $prefix.'-'.($normalized === '' ? '0' : str_replace('.', '_', $normalized));
    }
}
