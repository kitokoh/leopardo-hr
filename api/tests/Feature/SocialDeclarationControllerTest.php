<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SocialDeclarationControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_employee_cannot_generate_social_declarations(): void
    {
        [$company, , $employee] = $this->socialDeclarationActors();
        $this->validatedSlip($company, $employee, '2026-01-01');

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/social-declarations/cnas-dz', [
            'quarter' => 'Q1',
            'year' => 2026,
        ])->assertForbidden();
    }

    public function test_social_declaration_period_payload_is_validated(): void
    {
        [, $manager] = $this->socialDeclarationActors();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/social-declarations/cnas-dz', [
            'quarter' => 'Q5',
            'year' => 2019,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quarter', 'year']);

        $this->postJson('/api/v1/social-declarations/dsn-fr', [
            'month' => 13,
            'year' => 2026,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_manager_can_generate_cnas_dz_without_leaking_other_tenant_payroll(): void
    {
        [$company, $manager, $employee] = $this->socialDeclarationActors([
            'metadata' => ['nis' => 'DZ-NIS-778899'],
        ]);
        $this->validatedSlip($company, $employee, '2026-01-01', 120000, 90000);
        $this->validatedSlip($company, $employee, '2026-02-01', 130000, 97000);

        $inactive = Employee::factory()->archived()->create([
            'company_id' => $company->id,
            'national_id' => 'INACTIVE-NSS',
        ]);
        $this->validatedSlip($company, $inactive, '2026-01-01', 90000, 70000);

        [$otherCompany, , $otherEmployee] = $this->socialDeclarationActors();
        $this->validatedSlip($otherCompany, $otherEmployee, '2026-01-01', 500000, 400000);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/social-declarations/cnas-dz', [
            'quarter' => 'Q1',
            'year' => 2026,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.format', 'cnas_dz')
            ->assertJsonPath('data.employee_count', 1);

        $content = (string) $response->json('data.content');
        $this->assertStringContainsString('ENTETE|Leopardo DZ|DZ-NIS-778899|Q1|2026', $content);
        $this->assertStringContainsString('NSS-123456789|DOE|AMINA|1990-04-12|250000.00|2', $content);
        $this->assertStringContainsString('TOTAL|1|250000.00', $content);
        $this->assertStringNotContainsString('INACTIVE-NSS', $content);
        $this->assertStringNotContainsString('500000.00', $content);
    }

    public function test_manager_can_generate_cnss_ma_with_attendance_days(): void
    {
        [$company, $manager, $employee] = $this->socialDeclarationActors([
            'name' => 'Leopardo MA',
            'metadata' => ['affiliate_number' => 'MA-AFF-9988'],
        ]);
        $this->validatedSlip($company, $employee, '2026-04-01', 210000, 165000, 'MA');
        $this->attendanceDay($company, $employee, '2026-04-03 08:00:00');
        $this->attendanceDay($company, $employee, '2026-04-04 08:00:00');

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/social-declarations/cnss-ma', [
            'quarter' => 'Q2',
            'year' => 2026,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.format', 'cnss_ma')
            ->assertJsonPath('data.employee_count', 1);

        $content = (string) $response->json('data.content');
        $this->assertStringContainsString('ENTETE;Leopardo MA;MA-AFF-9988;Q2;2026', $content);
        $this->assertStringContainsString('SALARIE;000001;NSS-123456789;DOE;AMINA;;210000.00;2', $content);
        $this->assertStringContainsString('TOTAL;1;210000.00;2', $content);
    }

    public function test_manager_can_generate_dsn_fr_from_contract_start_and_company_metadata(): void
    {
        [$company, $manager, $employee] = $this->socialDeclarationActors([
            'name' => 'Leopardo France',
            'metadata' => ['siret' => '55210055400013'],
        ], [
            'contract_type' => 'CDD',
            'contract_start' => '2024-09-16',
        ]);
        $this->validatedSlip($company, $employee, '2026-01-01', 320000, 245000, 'FR');

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/social-declarations/dsn-fr', [
            'month' => 1,
            'year' => 2026,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.format', 'dsn_fr')
            ->assertJsonPath('data.employee_count', 1);

        $content = (string) $response->json('data.content');
        $this->assertStringContainsString("S20.G00.05.001,'55210055400013'", $content);
        $this->assertStringContainsString("S21.G00.30.001,'NSS-123456789'", $content);
        $this->assertStringContainsString("S21.G00.30.006,'1990-04-12'", $content);
        $this->assertStringContainsString("S21.G00.40.007,'02'", $content);
        $this->assertStringContainsString("S21.G00.40.001,'2024-09-16'", $content);
        $this->assertStringContainsString("S44.G00.00.001,'320000.00'", $content);
    }

    /**
     * @param  array<string, mixed>  $companyOverrides
     * @param  array<string, mixed>  $employeeOverrides
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function socialDeclarationActors(array $companyOverrides = [], array $employeeOverrides = []): array
    {
        $company = Company::factory()->create(array_merge([
            'name' => 'Leopardo DZ',
            'metadata' => [
                'nis' => 'DZ-NIS-123',
                'affiliate_number' => 'MA-AFF-123',
                'siret' => '55210055400013',
            ],
        ], $companyOverrides));

        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        $employee = Employee::factory()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
            'national_id' => 'NSS-123456789',
            'date_of_birth' => '1990-04-12',
            'contract_start' => '2025-01-10',
            'contract_type' => 'CDI',
            'status' => 'active',
        ], $employeeOverrides));

        return [$company, $manager, $employee];
    }

    private function validatedSlip(
        Company $company,
        Employee $employee,
        string $periodStart,
        float $grossSalary = 120000,
        float $netSalary = 90000,
        string $countryCode = 'DZ',
    ): PaySlip {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => $countryCode,
            'period_start' => $periodStart,
            'period_end' => Carbon::parse($periodStart)->endOfMonth()->toDateString(),
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => $grossSalary,
            'total_deductions' => $grossSalary - $netSalary,
            'total_net' => $netSalary,
            'validated_at' => now(),
        ]);

        return PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $periodStart,
            'period_end' => $run->period_end,
            'gross_salary' => $grossSalary,
            'total_deductions' => $grossSalary - $netSalary,
            'net_salary' => $netSalary,
            'employer_contributions' => round($grossSalary * 0.22, 2),
            'total_cost' => round($grossSalary * 1.22, 2),
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);
    }

    private function attendanceDay(Company $company, Employee $employee, string $checkIn): void
    {
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => substr($checkIn, 0, 10),
            'check_in' => $checkIn,
            'check_out' => Carbon::parse($checkIn)->addHours(8)->toDateTimeString(),
            'status' => 'ontime',
        ]);
    }
}
