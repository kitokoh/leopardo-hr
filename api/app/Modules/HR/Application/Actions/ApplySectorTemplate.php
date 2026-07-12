<?php

namespace App\Modules\HR\Application\Actions;

use App\Core\Auth\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use Illuminate\Support\Facades\DB;

class ApplySectorTemplate
{
    public function execute(Company $company, string $sector): void
    {
        if ($sector === 'btp') {
            $this->applyBtpTemplate($company->id);
        } elseif ($sector === 'security') {
            $this->applySecurityTemplate($company->id);
        }
    }

    private function applyBtpTemplate(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            // Horaires de chantier (par ex: 7h-15h)
            $scheduleId = DB::table('schedules')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Horaires de Chantier (BTP)',
                'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'break_duration_minutes' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Prime de panier et salissure
            DB::table('salary_components')->insert([
                [
                    'company_id' => $companyId,
                    'name' => 'Prime de panier',
                    'type' => 'allowance',
                    'amount' => 10.00,
                    'is_taxable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'company_id' => $companyId,
                    'name' => 'Prime de salissure',
                    'type' => 'allowance',
                    'amount' => 5.00,
                    'is_taxable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);

            // Absence intemperies
            DB::table('absence_types')->insert([
                'company_id' => $companyId,
                'name' => 'Intempéries',
                'is_paid' => true,
                'requires_approval' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function applySecurityTemplate(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            // Horaires de nuit
            $scheduleId = DB::table('schedules')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Horaires de Nuit (Sécurité)',
                'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'break_duration_minutes' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Prime de risque
            DB::table('salary_components')->insert([
                [
                    'company_id' => $companyId,
                    'name' => 'Prime de risque',
                    'type' => 'allowance',
                    'amount' => 50.00,
                    'is_taxable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'company_id' => $companyId,
                    'name' => 'Prime de nuit',
                    'type' => 'allowance',
                    'amount' => 100.00,
                    'is_taxable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        });
    }
}
