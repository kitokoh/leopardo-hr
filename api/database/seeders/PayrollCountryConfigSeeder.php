<?php

namespace Database\Seeders;

use App\Models\SocialContribution;
use App\Models\TaxSlab;
use App\Services\Payroll\CountryRules\AlgeriaPayrollRules;
use App\Services\Payroll\CountryRules\FrancePayrollRules;
use App\Services\Payroll\CountryRules\MoroccoPayrollRules;
use App\Services\Payroll\CountryRules\SenegalPayrollRules;
use App\Services\Payroll\CountryRules\TunisiaPayrollRules;
use App\Services\Payroll\CountryRules\TurkeyPayrollRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PayrollCountryConfigSeeder extends Seeder
{
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
        ];

        foreach ($rules as $countryRules) {
            $countryCode = $countryRules->countryCode();

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
                        'name' => $countryCode.' payroll tax 2026',
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
