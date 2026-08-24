<?php

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\UnitedKingdomPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\UnitedStatesPayrollRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PayrollCountryConfigSeeder extends Seeder
{
    /**
     * Date d'effet des barèmes par pays. Les pays seedés avec les taux des
     * CGI 2024 (Côte d'Ivoire #1825, Cameroun #1821, Burkina Faso, Mali
     * — issue #1829) → effective_from =
     * 2024-01-01 ; les autres pays gardent 2026-01-01 (comportement
     * historique).
     *
     * @var array<string, string>
     */
    private const EFFECTIVE_FROM_BY_COUNTRY = [
        'CI' => '2024-01-01',
        'BF' => '2024-01-01',
        'ML' => '2024-01-01',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('tax_slabs') || ! Schema::hasTable('social_contributions')) {
            $this->command?->warn('Payroll tables not found in the current schema; skipping payroll country config seeding.');

            return;
        }

        $rules = [
            new AlgeriaPayrollRules,
            new MoroccoPayrollRules,
            new TunisiaPayrollRules,
            new FrancePayrollRules,
            new TurkeyPayrollRules,
            new SenegalPayrollRules,
            // Côte d'Ivoire (CEDEAO) — règles pilotes ITSAS/CN/CNSS (issue #1825),
            // seedées avec les taux du CGI 2024 (effective_from = 2024-01-01).
            new CedeaoPayrollRules('CI'),
            // Burkina Faso + Mali (CEDEAO) — règles pilotes IUTS/ITS + CNSS/INPS
            // (issue #1829).
            new CedeaoPayrollRules('BF'),
            new CedeaoPayrollRules('ML'),
            // CM (#1821) : barèmes IRPP CGI 2024 + CNPS (pilot) seedés comme
            // les autres pays — les autres membres CEMAC restent placeholder
            // (pas de barèmes légaux à seed) jusqu'à leurs issues (#1824...).
            (new CemacPayrollRules)->forMemberCountry('CM'),
            // Packs EN (#5255) : GB/US (pilot 2026-27) + Canada audité 2026
            // (CPP/CPP2/EI plafonnés, barème fédéral 14 %).
            new UnitedKingdomPayrollRules,
            new UnitedStatesPayrollRules,
            new CanadaPayrollRules,
        ];

        foreach ($rules as $countryRules) {
            $countryCode = $countryRules->countryCode();
            $effectiveFrom = self::EFFECTIVE_FROM_BY_COUNTRY[$countryCode] ?? '2026-01-01';

            foreach ($countryRules->socialContributions() as $contribution) {
                SocialContribution::updateOrCreate(
                    [
                        'company_id' => null,
                        'code' => $contribution['code'],
                    ],
                    [
                        'country_code' => $countryCode,
                        'name' => $contribution['name'],
                        'type' => $contribution['type'],
                        'rate' => $contribution['rate'],
                        'cap' => $contribution['cap'],
                        'effective_from' => $effectiveFrom,
                        'effective_to' => null,
                        // Issue #1813 : la config nationale de référence est
                        // officielle → active (contourne le workflow de
                        // validation réservé aux modifications runtime).
                        'status' => SocialContribution::STATUS_ACTIVE,
                    ]
                );
            }

            // Issue #2003 : seeder depuis la source LÉGALE (code), pas depuis
            // `taxSlabs()` qui résout la base AVANT le code (re-seed = no-op
            // silencieux quand la base diverge du code).
            foreach ($countryRules->legalReferenceTaxSlabs() as $slab) {
                TaxSlab::updateOrCreate(
                    [
                        'company_id' => null,
                        'country_code' => $countryCode,
                        'name' => $countryCode.' payroll tax '.substr($effectiveFrom, 0, 4),
                        'min_amount' => $slab['min'],
                    ],
                    [
                        'max_amount' => $slab['max'],
                        'rate' => $slab['rate'],
                        'fixed_deduction' => $slab['fixed_deduction'],
                        // Issue #1932 : le barème est seedé avec le
                        // effective_from annoncé par le name (« 2024 » pour
                        // BF/ML/CI/CM) — pas une date en dur 2026-01-01 qui
                        // casserait le recalcul rétroactif asOf().
                        'effective_from' => $effectiveFrom,
                        'effective_to' => null,
                        // Issue #1813 : config nationale officielle → active.
                        'status' => TaxSlab::STATUS_ACTIVE,
                    ]
                );
            }
        }
    }
}
