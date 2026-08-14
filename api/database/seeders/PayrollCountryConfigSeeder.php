<?php

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PayrollCountryConfigSeeder extends Seeder
{
    /**
     * Date d'effet des barèmes par pays. Les pays seedés avec les taux des
     * CGI 2024 (Côte d'Ivoire #1825, Cameroun #1821) → effective_from =
     * 2024-01-01 ; les autres pays gardent 2026-01-01 (comportement
     * historique).
     *
     * @var array<string, string>
     */
    private const EFFECTIVE_FROM_BY_COUNTRY = [
        'CI' => '2024-01-01',
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
                        'effective_from' => '2026-01-01',
                        'effective_to' => null,
                    ]
                );
            }

            foreach ($countryRules->taxSlabs() as $slab) {
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
                        'effective_from' => '2026-01-01',
                        'effective_to' => null,
                    ]
                );
            }
        }
    }
}


