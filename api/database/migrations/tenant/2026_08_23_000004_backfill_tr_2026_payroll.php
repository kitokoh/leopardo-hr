<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5253 — TR : remplacement du barème gelir vergisi 2024 par le
 * barème salariés 2026 et mise à jour des cotisations SGK/chômage 2026.
 *
 * `TurkeyPayrollRules::defaultTaxSlabs()` porte le nouveau barème (GVK
 * art. 103 — RG 31/12/2025), mais les tenants TR seedés gardent les 5
 * tranches ANNEES 2024 (min_amount 0/110 001/230 001/580 001/3 000 001)
 * en base : `AbstractCountryRules::taxSlabs()` résout la base AVANT le
 * code. Sans backfill, les bulletins TR existants continueraient
 * d'utiliser un barème abrogé (et un re-seed simple ajouterait les 5
 * nouvelles tranches SANS retirer les anciennes → 10 lignes actives
 * chevauchantes → progressif corrompu).
 *
 * Même pattern que `2026_08_14_000018_backfill_ci_its_2024.php` :
 *  - détection de l'ANCIEN shape par min_amount caractéristiques ;
 *  - suppression des seules lignes de l'ancien barème (lignes custom
 *    admin préservées) ;
 *  - insertion des 5 tranches 2026.
 *
 * Idempotente (gardes schemaTableExists + détection shape) : après
 * remplacement, plus aucune ligne ne matche les anciens min_amount →
 * no-op. Les cotisations (taux employeur 20,5 % → 21,75 %, plafond
 * tavan 297 270 TRY sur toutes les cotisations) sont mises à jour pour
 * les scopes TR existants.
 */
return new class extends Migration
{
    private const COUNTRY = 'TR';

    /** Ancien barème 2024 — min_amount caractéristiques. */
    private const OLD_MIN_AMOUNTS = [110_001, 230_001, 580_001, 3_000_001];

    /** Nouveau barème salariés 2026 (GVK art. 103, RG 31/12/2025). */
    private const NEW_SLABS = [
        ['min' => 0, 'max' => 190_000, 'rate' => 15],
        ['min' => 190_001, 'max' => 400_000, 'rate' => 20],
        ['min' => 400_001, 'max' => 1_500_000, 'rate' => 27],
        ['min' => 1_500_001, 'max' => 5_300_000, 'rate' => 35],
        ['min' => 5_300_001, 'max' => null, 'rate' => 40],
    ];

    /**
     * Taux/plafonds SGK + işsizlik 2026 (CSGB/SGK, RG 31/12/2025).
     * Code → [rate, cap mensuel (tavan 297 270 TRY)].
     *
     * @var array<string, array{rate: float, cap: float|null}>
     */
    private const CONTRIBUTIONS_2026 = [
        'SGK_TR_EMP' => ['rate' => 14.0, 'cap' => 297_270.0],
        'SGK_TR_PAT' => ['rate' => 21.75, 'cap' => 297_270.0],
        'UNEMP_TR_EMP' => ['rate' => 1.0, 'cap' => 297_270.0],
        'UNEMP_TR_PAT' => ['rate' => 2.0, 'cap' => 297_270.0],
    ];

    public function up(): void
    {
        if (! schemaTableExists('tax_slabs')) {
            return;
        }

        $scopes = $this->trSlabScopes();

        foreach ($scopes as $scope) {
            $this->replaceScope($scope['company_id'], $scope['effective_from']);
        }

        if (schemaTableExists('social_contributions')) {
            $this->updateContributions();
        }
    }

    public function down(): void
    {
        // Pas de rollback : l'ancien barème 2024 est abrogé depuis le
        // 01/01/2026 — revenir en arrière réintroduirait un barème illégal.
    }

    /**
     * Scopes distincts de la table TR : (company_id, effective_from de
     * référence) pour chaque ensemble de tranches cohérent.
     *
     * @return array<int, array{company_id: int|string|null, effective_from: string}>
     */
    private function trSlabScopes(): array
    {
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
                    : '2026-01-01', // barème TR 2026 (#5253)
            ];
        }

        // Garde : au moins le scope national doit exister (seed).
        if ($scopes === []) {
            $scopes[] = ['company_id' => null, 'effective_from' => '2026-01-01'];
        }

        return $scopes;
    }

    /**
     * Remplace l'ancien barème TR d'un scope par le barème 2026.
     * Les lignes custom (min_amount hors ancien shape) sont conservées.
     */
    private function replaceScope(int|string|null $companyId, string $effectiveFrom): void
    {
        $base = DB::table('tax_slabs')
            ->where('country_code', self::COUNTRY)
            ->where('company_id', $companyId);

        // 1. L'ancien barème est-il présent ? (min_amount caractéristiques)
        $hasOldShape = (clone $base)
            ->whereIn('min_amount', self::OLD_MIN_AMOUNTS)
            ->exists();

        if (! $hasOldShape) {
            // Déjà migré (no-op) ou aucune ligne seedée.
            return;
        }

        // 2. Suppression des lignes de l'ANCIEN barème uniquement (shape
        //    exact : les 4 min_amount caractéristiques + la ligne 0 de
        //    l'ancien shape 0-110 000 @ 15 %). Les lignes custom admin
        //    (autres min_amount/taux) ne sont pas touchées.
        (clone $base)->whereIn('min_amount', self::OLD_MIN_AMOUNTS)->delete();
        (clone $base)
            ->where('min_amount', 0)
            ->where('max_amount', 110_000)
            ->where('rate', 15)
            ->delete();

        // 3. Insertion des 5 tranches annuelles salariés 2026.
        $year = (int) substr($effectiveFrom, 0, 4);
        $now = now();
        foreach (self::NEW_SLABS as $slab) {
            (clone $base)->insert([
                'company_id' => $companyId,
                'country_code' => self::COUNTRY,
                'name' => self::COUNTRY.' payroll tax '.$year,
                'min_amount' => $slab['min'],
                'max_amount' => $slab['max'],
                'rate' => $slab['rate'],
                'fixed_deduction' => 0,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Met à jour les cotisations TR des scopes existants vers les
     * taux/plafonds 2026 (remplace l'ancien taux employeur 20,5 % sans
     * plafond par 21,75 % plafonné au tavan, aligne les caps).
     */
    private function updateContributions(): void
    {
        foreach (self::CONTRIBUTIONS_2026 as $code => $config) {
            DB::table('social_contributions')
                ->where('country_code', self::COUNTRY)
                ->where('code', $code)
                ->where('status', 'active')
                ->update([
                    'rate' => $config['rate'],
                    'cap' => $config['cap'],
                    'updated_at' => now(),
                ]);
        }
    }
};
