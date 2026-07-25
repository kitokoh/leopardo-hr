<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SectorTemplateService
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator = new PayrollCalculator,
    ) {}

    public function applyTemplate(Company $company): void
    {
        $sector = strtolower($company->sector ?? 'standard');

        $this->seedAbsenceTypes($company->id, $sector);
        $this->seedPositions($company->id, $sector);
        $this->seedDefaultSchedule($company);
    }

    /**
     * PA2-COUNTRY-002: the company creation flow already derives currency
     * and timezone from the chosen country (see CountryDefaults), but was
     * missing the "HR rule model" part of the acceptance criteria: a new
     * company had no default work Schedule at all, so attendance/overtime
     * calculations had nothing to fall back on until a manager manually
     * created one from scratch with generic 9-to-5/Mon-Fri values that may
     * not match local labor law (e.g. France's 35h week, Tunisia's Sunday
     * rest day, Morocco's 44h threshold).
     *
     * This seeds one `is_default` Schedule per new company, using the
     * country's own CountryRulesInterface (weeklyRestDays(),
     * overtimeThresholdWeeklyHours()) so the starting point already
     * matches that country's standard labor-code baseline. Managers can
     * still edit/replace it afterwards via the Schedule CRUD API.
     *
     * Idempotent: does nothing if a default schedule already exists for
     * this company (e.g. re-applying the template, or a schedule created
     * by another provisioning path first).
     */
    private function seedDefaultSchedule(Company $company): void
    {
        if (! $this->sharedTableExists('schedules')) {
            return;
        }

        $alreadyHasDefault = DB::table($this->tenantTable('schedules'))
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->exists();

        if ($alreadyHasDefault) {
            return;
        }

        $countryCode = strtoupper((string) ($company->country ?? ''));
        $restDays = [7]; // Sunday-only fallback, matching most supported countries.
        $overtimeThresholdWeekly = 40.0;

        try {
            $rules = $this->payrollCalculator->getRules($countryCode);
            $restDays = $this->normalizeRestDays($rules);
            $overtimeThresholdWeekly = $rules->overtimeThresholdWeeklyHours();
        } catch (\InvalidArgumentException) {
            // Country not registered in PayrollCalculator's rules map yet:
            // keep the generic Sunday-rest/40h fallback above rather than
            // failing company provisioning over a missing country ruleset.
        }

        $workDays = array_values(array_diff([1, 2, 3, 4, 5, 6, 7], $restDays));
        $overtimeThresholdDaily = round($overtimeThresholdWeekly / max(count($workDays), 1), 2);

        DB::table($this->tenantTable('schedules'))->insert([
            'company_id' => $company->id,
            'name' => 'Horaire standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => json_encode($workDays),
            'rest_days' => json_encode($restDays),
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => $overtimeThresholdDaily,
            'overtime_threshold_weekly' => $overtimeThresholdWeekly,
            'is_default' => true,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeRestDays(CountryRulesInterface $rules): array
    {
        $restDays = array_values(array_unique(array_map('intval', $rules->weeklyRestDays())));
        $restDays = array_values(array_filter($restDays, static fn (int $day): bool => $day >= 1 && $day <= 7));

        return $restDays === [] ? [7] : $restDays;
    }

    private function seedAbsenceTypes(string $companyId, string $sector): void
    {
        $types = [
            ['company_id' => $companyId, 'name' => 'Congé Annuel', 'code' => 'CA', 'is_paid' => true, 'deducts_leave' => true, 'requires_proof' => false],
            ['company_id' => $companyId, 'name' => 'Maladie', 'code' => 'MAL', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            ['company_id' => $companyId, 'name' => 'Maternité', 'code' => 'MAT', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            ['company_id' => $companyId, 'name' => 'Paternité', 'code' => 'PAT', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            ['company_id' => $companyId, 'name' => 'Congé Sans Solde', 'code' => 'CSS', 'is_paid' => false, 'deducts_leave' => false, 'requires_proof' => false],
        ];

        if ($sector === 'btp' || $sector === 'construction') {
            $types[] = ['company_id' => $companyId, 'name' => 'Intempéries', 'code' => 'INT', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => false];
            $types[] = ['company_id' => $companyId, 'name' => 'Chômage Technique', 'code' => 'CHOM', 'is_paid' => false, 'deducts_leave' => false, 'requires_proof' => false];
        }

        DB::table($this->tenantTable('absence_types'))->insertOrIgnore($types);
    }

    private function seedPositions(string $companyId, string $sector): void
    {
        // On s'assure d'abord que la table positions existe
        if (! $this->sharedTableExists('positions')) {
            return;
        }

        $departmentId = null;
        if ($this->sharedColumnExists('positions', 'department_id')) {
            $departmentId = $this->resolveDefaultDepartmentId($companyId);

            if ($departmentId === null) {
                return;
            }
        }

        $positions = [];

        if ($sector === 'btp' || $sector === 'construction') {
            $positions = ['Ouvrier', 'Chef de Chantier', 'Conducteur de Travaux', 'Maçon', 'Électricien'];
        } elseif ($sector === 'sécurité' || $sector === 'securite' || $sector === 'security') {
            $positions = ['Agent de Sécurité', 'Superviseur', 'Rondier', 'Chef de Poste'];
        } else {
            $positions = ['Employé', 'Manager', 'Directeur'];
        }

        $insertData = [];
        foreach ($positions as $position) {
            $insertData[] = [
                'company_id' => $companyId,
                'name' => $position,
                ...($departmentId !== null ? ['department_id' => $departmentId] : []),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table($this->tenantTable('positions'))->insertOrIgnore($insertData);
    }

    private function resolveDefaultDepartmentId(string $companyId): ?int
    {
        if (! $this->sharedTableExists('departments')) {
            return null;
        }

        $departmentHasCompany = $this->sharedColumnExists('departments', 'company_id');

        $existingId = DB::table($this->tenantTable('departments'))
            ->when($departmentHasCompany, fn ($query) => $query->where('company_id', $companyId))
            ->where('name', 'Operations')
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        $fallbackId = DB::table($this->tenantTable('departments'))
            ->when($departmentHasCompany, fn ($query) => $query->where('company_id', $companyId))
            ->value('id');

        if ($fallbackId !== null) {
            return (int) $fallbackId;
        }

        $payload = [
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($departmentHasCompany) {
            $payload['company_id'] = $companyId;
        }

        return (int) DB::table($this->tenantTable('departments'))->insertGetId($payload);
    }

    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }

    private function sharedTableExists(string $table): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable($table);
        }

        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.tables
                where table_schema = ?
                  and table_name = ?
            ) as exists',
            ['shared_tenants', $table]
        );

        return (bool) ($result->exists ?? false);
    }

    private function sharedColumnExists(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasColumn($table, $column);
        }

        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.columns
                where table_schema = ?
                  and table_name = ?
                  and column_name = ?
            ) as exists',
            ['shared_tenants', $table, $column]
        );

        return (bool) ($result->exists ?? false);
    }
}
