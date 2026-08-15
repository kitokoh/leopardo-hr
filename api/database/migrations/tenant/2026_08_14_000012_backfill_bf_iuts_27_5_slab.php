<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2003 — BF : tranche IUTS 27,5 % (> 6 000 000 FCFA/an) absente des
 * tax_slabs SEEDÉS en base.
 *
 * Le fix #1972 a rétabli la 6e tranche dans `CedeaoPayrollRules::defaultTaxSlabs()`
 * (le code), mais les tenants BF seedés depuis #1829 gardent 5 tranches en
 * base : `AbstractCountryRules::taxSlabs()` résout la base AVANT le code, et
 * `PayrollCountryConfigSeeder` re-seedait depuis `taxSlabs()` (base) → no-op
 * silencieux. Les bulletins BF > ~500 000 FCFA/mois restaient sous-imposés
 * (marginal 23,6 % au lieu de 27,5 %).
 *
 * Cette migration backfille les lignes existantes (nationales ET par
 * entreprise) :
 *   1. tranche `4 500 001` : `max_amount` → `6 000 000` (elle était
 *      fusionnée avec la tranche finale, `max_amount = NULL`) ;
 *   2. insertion de la tranche `6 000 001 → NULL @ 27,5 %` (status active,
 *      effective_from aligné sur les lignes existantes du même scope).
 *
 * Idempotente (gardes F-17 / schemaTableExists) : rejouable sur Render sans
 * doublon ni échec.
 */
return new class extends Migration
{
    private const COUNTRY = 'BF';

    public function up(): void
    {
        if (! schemaTableExists('tax_slabs')) {
            return;
        }

        $scopes = $this->bfSlabScopes();

        // Issue #2153 (CI) : si la table ne contient AUCUNE ligne BF (base
        // fraîche, seed #1829 pas encore exécuté — ex. bases de test), il n'y
        // a rien à backfiller : on sort sans rien insérer. Avant ce garde-fou,
        // `bfSlabScopes()` ajoutait un scope national synthétique qui insérait
        // une tranche ORPHELINE unique (6 000 001 → NULL @ 27,5 %) dans une
        // table vide — `taxSlabs()` résolvait alors ce barème partiel depuis
        // la base (1 seule tranche) → tout revenu annuel < 6 000 000 était
        // taxé à 0,0 (échec GoldenBfPayrollTest::test_golden_bf_cadre_300k_iuts
        // : 0,0 attendu 32 714,50). Sur table vide, les défauts du code
        // (defaultTaxSlabs) restent la source de vérité.
        if ($scopes === []) {
            return;
        }

        foreach ($scopes as $scope) {
            $this->repairScope($scope['company_id'], $scope['effective_from']);
        }
    }

    public function down(): void
    {
        // Pas de rollback : le backfill corrige une sous-imposition légale —
        // revenir en arrière re-casserait les bulletins BF existants.
    }

    /**
     * Scopes distincts de la table BF : (company_id, effective_from de
     * référence) pour chaque ensemble de tranches cohérent.
     *
     * @return array<int, array{company_id: int|string|null, effective_from: string}>
     */
    private function bfSlabScopes(): array
    {
        // National + par entreprise.
        $rows = DB::table('tax_slabs')
            ->where('country_code', self::COUNTRY)
            ->select('company_id')
            ->distinct()
            ->orderBy('company_id')
            ->get();

        $scopes = [];
        foreach ($rows as $row) {
            $companyId = $row->company_id;
            $effectiveFrom = DB::table('tax_slabs')
                ->where('country_code', self::COUNTRY)
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->orderBy('effective_from')
                ->value('effective_from');

            $scopes[] = [
                'company_id' => $companyId,
                'effective_from' => $effectiveFrom !== null
                    ? (string) $effectiveFrom
                    : '2024-01-01', // barème BF CGI 2024 (#1829)
            ];
        }

        return $scopes;
    }

    /**
     * Répare les tranches d'un scope (company_id donné) : borne la tranche
     * 4 500 001 à 6 000 000 puis insère la tranche 27,5 % si absente.
     */
    private function repairScope(int|string|null $companyId, string $effectiveFrom): void
    {
        $base = DB::table('tax_slabs')
            ->where('country_code', self::COUNTRY)
            ->where('company_id', $companyId);

        // 1. La tranche 4 500 001 (taux 23,6 %) est bornée à 6 000 000 — elle
        //    était fusionnée avec la tranche finale (max_amount NULL).
        $merged = (clone $base)->where('min_amount', 4_500_001)->whereNull('max_amount')->first();
        if ($merged !== null) {
            (clone $base)->where('min_amount', 4_500_001)->whereNull('max_amount')->update([
                'max_amount' => 6_000_000,
                'updated_at' => now(),
            ]);
        }

        // 2. Insertion de la tranche 6 000 001 → NULL @ 27,5 % (CGI BF 2024),
        //    alignée sur l'effective_from du scope.
        $exists = (clone $base)->where('min_amount', 6_000_001)->exists();
        if (! $exists) {
            (clone $base)->insert([
                'company_id' => $companyId,
                'country_code' => self::COUNTRY,
                'name' => self::COUNTRY.' payroll tax '.substr($effectiveFrom, 0, 4),
                'min_amount' => 6_000_001,
                'max_amount' => null,
                'rate' => 27.5,
                'fixed_deduction' => 0,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
