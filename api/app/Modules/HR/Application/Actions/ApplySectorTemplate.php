<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
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

    private function applyBtpTemplate(string $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            // Horaires de chantier (par ex: 7h-15h)
            $scheduleId = DB::table('schedules')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Horaires de Chantier (BTP)',
                'work_days' => json_encode([1, 2, 3, 4, 5]), // lundi→vendredi (schéma réel work_days jsonb [1-7])
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'break_minutes' => 60,
                'created_at' => now(),
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
                ],
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

    private function applySecurityTemplate(string $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            // Horaires de nuit
            $scheduleId = DB::table('schedules')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Horaires de Nuit (Sécurité)',
                'work_days' => json_encode([1, 2, 3, 4, 5, 6, 7]),
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'break_minutes' => 30,
                'created_at' => now(),
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
                ],
            ]);
        });
    }
}
