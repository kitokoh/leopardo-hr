<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface as CountryRulesContract;

/**
 * #9878 (R7) — décomposition de l'impôt par tranche.
 *
 * Extrait de `PaySlipValueCalculator` (1 051 lignes), qui portait à la fois :
 * résolution des règles pays, tranches d'impôt, prorata, heures sup,
 * indemnités, jours fériés, ancienneté. Ce service ne fait qu'UNE chose :
 * produire le détail par tranche qui converge vers l'impôt réellement calculé
 * par le moteur.
 *
 * Comportement STRICTEMENT identique à l'implémentation d'origine (copie) :
 * les golden tests multi-pays de paie servent de filet de non-régression.
 *
 * Pourquoi plusieurs candidats : l'assiette ET la convention (mensuelle /
 * annualisée) varient par pays (CI : ITS sur le brut mensuel ; DZ :
 * progressif sur le net fiscal mensuel ; MA/FR : annualisé). On évalue les
 * candidats et on retient celui dont le total est le plus proche de
 * `$expectedTax`, puis on répartit l'écart pour que Σ(tranches) == impôt réel.
 */
class SlabTaxBreakdownCalculator
{
    /**
     * @return array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>
     */
    public function breakdown(
        CountryRulesContract $rules,
        float $gross,
        float $taxBase,
        float $expectedTax,
    ): array {
        $candidates = [
            // Candidats historiques : assiette brute/imposable × convention
            // mensuelle ou annualisée, sans abattement.
            $this->slabsFromAnnualBase($rules, $gross, 1.0),
            $this->slabsFromAnnualBase($rules, $taxBase, 1.0),
            $this->slabsFromAnnualBase($rules, $gross * 12.0, 12.0),
            $this->slabsFromAnnualBase($rules, $taxBase * 12.0, 12.0),
            // Issue #6727 : candidats alignés sur le pipeline réel — abattement
            // frais professionnels pré-barème (SN 30 % plafonné 75 000, MA 25 %,
            // TN 10 % borné, CM 30 % plafonné 350 000, TG 28 %, GA 20 %/833 333)
            // + facteur de surcharge (CM centimes additionnels × 1,10). Sans eux,
            // le détail par tranche ne convergeait jamais vers income_tax pour
            // les pays à abattement (repro #6727 : SN Δ 22 500, DZ Δ 1 500, MA Δ 950).
            $this->slabsFromAnnualBase($rules, $rules->effectiveAnnualTaxableBase($taxBase, 12.0, $gross), 12.0),
            $this->slabsFromAnnualBase($rules, $rules->effectiveAnnualTaxableBase($taxBase, 1.0, $gross), 1.0),
        ];

        // Issue #6727 : candidat post-barème (DZ) — réduction d'impôt annuelle
        // (IRG 40 % borné [12 000 ; 18 000]) répartie proportionnellement sur
        // les tranches pour que Σ tax == income_tax. Le barème algérien est
        // MENSUEL : on part de l'assiette mensuelle effective (jamais
        // annualisée — annualiser avec des bornes mensuelles fausserait les
        // tranches), l'impôt annuel servant uniquement au calcul de la réduction.
        $monthlyTaxBaseSlabs = $this->slabsFromAnnualBase($rules, $rules->effectiveAnnualTaxableBase($taxBase, 1.0, $gross), 1.0);
        $annualTax = array_sum(array_column($monthlyTaxBaseSlabs, 'tax')) * 12.0;
        $reduction = $rules->annualTaxReduction($annualTax);
        if ($reduction > 0.0 && $annualTax > 0.0) {
            $candidates[] = $this->applyAnnualTaxReduction(
                $monthlyTaxBaseSlabs,
                min($reduction, $annualTax),
            );
        }

        $best = $candidates[0];
        $bestDelta = PHP_FLOAT_MAX;
        foreach ($candidates as $candidate) {
            $delta = abs(array_sum(array_column($candidate, 'tax')) - $expectedTax);
            if ($delta < $bestDelta) {
                $best = $candidate;
                $bestDelta = $delta;
            }
        }

        // #6727 : convergence garantie du détail vers l'impôt réel. Les règles
        // pays à abattement (SN 30 % plafonné 75 000/mois, MA 25 %, DZ
        // réduction post-impôt 12–18 k/an, CM/CI…) appliquent la déduction
        // DANS calculateIncomeTax() — aucun des candidats bruts ci-dessus ne la
        // reflète, donc Σ(taxes par tranche) divergeait de `income_tax`
        // (constat prod : SN 300 000/mois → 61 960 affiché vs 39 460 réel).
        // On répartit l'impôt réel proportionnellement aux tranches du meilleur
        // candidat. Le détail reste indicatif : `income_tax` fait foi.
        $bestTotal = array_sum(array_column($best, 'tax'));

        if ($bestTotal > 0.0 && abs($bestTotal - $expectedTax) > 0.01) {
            $scale = $expectedTax / $bestTotal;
            $best = array_map(static fn (array $slab): array => [
                ...$slab,
                'tax' => round($slab['tax'] * $scale, 2),
            ], $best);
        } elseif ($bestTotal <= 0.0 && $expectedTax > 0.0 && $best !== []) {
            // Taxe de minimum fiscal (ex. TRIMF SN) : aucun candidat ne produit
            // de tranche positive — porter l'impôt réel sur la tranche
            // supérieure pour garder Σ == income_tax.
            $last = array_key_last($best);
            $best[$last]['tax'] = round($expectedTax, 2);
        }

        // Absorbe l'écart d'arrondi (Σ doit valoir income_tax exactement).
        $sum = array_sum(array_column($best, 'tax'));
        $roundingDiff = round($expectedTax - $sum, 2);
        if (abs($roundingDiff) > 0.0 && $best !== []) {
            $last = array_key_last($best);
            $best[$last]['tax'] = round($best[$last]['tax'] + $roundingDiff, 2);
        }

        return $best;
    }

    /**
     * Décompose une base ANNUELLE en tranches mensualisées (divisées par
     * $annualBasis), en appliquant le facteur de surcharge de la règle pays
     * (ex. centimes additionnels CM). Le montant affiché par tranche est
     * mensuel (contrat `income_tax_by_slab` du simulateur).
     *
     * @return array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>
     */
    private function slabsFromAnnualBase(CountryRulesContract $rules, float $annualBase, float $annualBasis): array
    {
        $surcharge = $rules->taxSurchargeFactor();
        $bySlab = [];

        foreach ($rules->taxSlabs() as $slab) {
            $lowerBound = (float) $slab['min'];
            if ($lowerBound > 0) {
                $lowerBound -= 1;
            }
            $upperBound = $slab['max'] === null ? PHP_FLOAT_MAX : (float) $slab['max'];
            $taxableInSlab = min($annualBase, $upperBound) - $lowerBound;
            if ($taxableInSlab <= 0) {
                continue;
            }
            $slabTax = round($taxableInSlab * ((float) $slab['rate'] / 100) * $surcharge, 2);

            $bySlab[] = [
                'min' => (float) $slab['min'],
                'max' => $slab['max'],
                'rate' => (float) $slab['rate'],
                'taxable_amount' => round($taxableInSlab / $annualBasis, 2),
                'tax' => round($slabTax / $annualBasis, 2),
            ];
        }

        return $bySlab;
    }

    /**
     * Répartit une réduction d'impôt ANNUELLE proportionnellement sur les
     * tranches (montants mensuels) pour préserver Σ tax == income_tax.
     *
     * @param  array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>  $slabs
     * @return array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>
     */
    private function applyAnnualTaxReduction(array $slabs, float $annualReduction): array
    {
        $monthlyTotal = array_sum(array_column($slabs, 'tax'));
        if ($monthlyTotal <= 0.0) {
            return $slabs;
        }

        $factor = 1.0 - $annualReduction / ($monthlyTotal * 12.0);
        foreach ($slabs as &$slab) {
            $slab['tax'] = round($slab['tax'] * $factor, 2);
        }
        unset($slab);

        return $slabs;
    }
}
