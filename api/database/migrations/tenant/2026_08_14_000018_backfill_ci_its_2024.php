<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1918 — CI : remplacement du barème ITSAS annuel pré-réforme par
 * l'ITS unifié mensuel 2024 (ordonnance 2023-718/719, effet 01/01/2024,
 * CGI art. 119 bis).
 *
 * `CedeaoPayrollRules::defaultTaxSlabs()` porte le nouveau barème, mais les
 * tenants CI seedés depuis #1825 gardent les 5 tranches ANNUELLES de
 * l'ancien ITSAS (0/2/21/24,5/29 % avec min_amount 0/600 001/2 000 001/
 * 5 000 001/10 000 001) en base : `AbstractCountryRules::taxSlabs()`
 * résout la base AVANT le code. Sans backfill, les bulletins CI existants
 * continueraient d'utiliser un barème abrogé (sur-taxation 10–25 M,
 * sous-taxation > 25 M, CN 1,5 % encore déduite).
 *
 * Cette migration remplace, pour chaque scope CI (national + par
 * entreprise), les lignes au SHAPE de l'ancien barème par les 6 tranches
 * mensuelles du barème 2024 (0/75 001/240 001/800 001/2 400 001/8 000 001,
 * taux 0/16/21/24/28/32), effectives à partir de l'effective_from du scope
 * (défaut 2024-01-01).
 *
 * Idempotente (gardes F-17 / schemaTableExists) : les lignes aux min_amount
 * 600 001/2 000 001/5 000 001/10 000 001 n'existent que dans l'ancien
 * barème — après remplacement, plus aucune ne correspond → no-op. Les
 * lignes custom admin (min_amount hors ancien shape) sont préservées.
 */
return new class extends Migration
{
    private const COUNTRY = 'CI';

    /** Ancien barème ITSAS annuel (pré-réforme) — min_amount caractéristiques. */
    private const OLD_MIN_AMOUNTS = [600_001, 2_000_001, 5_000_001, 10_000_001];

    /** Nouveau barème ITS unifié mensuel 2024 (art. 119 bis). */
    private const NEW_SLABS = [
        ['min' => 0, 'max' => 75_000, 'rate' => 0],
        ['min' => 75_001, 'max' => 240_000, 'rate' => 16],
        ['min' => 240_001, 'max' => 800_000, 'rate' => 21],
        ['min' => 800_001, 'max' => 2_400_000, 'rate' => 24],
        ['min' => 2_400_001, 'max' => 8_000_000, 'rate' => 28],
        ['min' => 8_000_001, 'max' => null, 'rate' => 32],
    ];

    public function up(): void
    {
        if (! schemaTableExists('tax_slabs')) {
            return;
        }

        $scopes = $this->ciSlabScopes();

        foreach ($scopes as $scope) {
            $this->replaceScope($scope['company_id'], $scope['effective_from']);
        }
    }

    public function down(): void
    {
        // Pas de rollback : l'ancien barème est abrogé depuis le 01/01/2024 —
        // revenir en arrière réintroduirait un barème illégal.
    }

    /**
     * Scopes distincts de la table CI : (company_id, effective_from de
     * référence) pour chaque ensemble de tranches cohérent.
     *
     * @return array<int, array{company_id: int|string|null, effective_from: string}>
     */
    private function ciSlabScopes(): array
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
                    : '2024-01-01', // barème ITS CI 2024 (#1825/#1918)
            ];
        }

        // Garde : au moins le scope national doit exister (seed #1825).
        if ($scopes === []) {
            $scopes[] = ['company_id' => null, 'effective_from' => '2024-01-01'];
        }

        return $scopes;
    }

    /**
     * Remplace l'ancien barème ITSAS d'un scope par le barème ITS 2024.
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
        //    exact : les 5 min_amount + taux associés) — les lignes custom
        //    admin (autres min_amount) ne sont pas touchées.
        (clone $base)->whereIn('min_amount', self::OLD_MIN_AMOUNTS)->delete();

        // 3. Insertion des 6 tranches mensuelles ITS 2024.
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
};
