<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\CandidateHired;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5261 — parcours candidat → employé tracé (DoD : un recrutement
 * simulé aboutit à un bulletin de paie).
 *
 * Périmètre HR : le service lit l'Applicant (Recruitment) en lecture
 * seule, crée l'Employee via EmployeeService (traçabilité candidate_id),
 * sous gardes anti-doublon et onboarding obligatoire.
 */
class CandidateHiringTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->manager = $manager;
    }

    private function makeApplicant(string $status = 'screening'): Applicant
    {
        /** @var JobPosting $jobPosting */
        $jobPosting = JobPosting::query()->create([
            'company_id' => $this->company->id,
            'title' => 'Comptable DZ',
            'description' => 'Poste de comptable',
            'contract_type' => 'cdi',
            'currency' => 'DZD',
            'status' => 'published',
        ]);

        /** @var Applicant $applicant */
        $applicant = Applicant::query()->create([
            'company_id' => $this->company->id,
            'job_posting_id' => $jobPosting->id,
            'first_name' => 'Amine',
            'last_name' => 'Cherif',
            'email' => 'amine.cherif@hire.test',
            'phone' => '+213555123456',
            'source' => 'linkedin',
            'status' => $status,
            'applied_at' => now(),
        ]);

        return $applicant;
    }

    private function completeOnboarding(): void
    {
        // Les steps obligatoires de l'entreprise sont complétés.
        OnboardingStep::query()->create([
            'company_id' => $this->company->id,
            'step_key' => 'company_profile',
            'title' => 'Profil entreprise',
            'status' => 'completed',
            'required' => true,
            'order' => 1,
        ]);
        OnboardingStep::query()->create([
            'company_id' => $this->company->id,
            'step_key' => 'payroll_setup',
            'title' => 'Paramétrage paie',
            'status' => 'completed',
            'required' => true,
            'order' => 2,
        ]);
    }

    public function test_hire_creates_employee_with_candidate_traceability(): void
    {
        Event::fake([CandidateHired::class]);
        $this->completeOnboarding();
        $applicant = $this->makeApplicant();
        Sanctum::actingAs($this->manager, ['*']);

        $response = $this->postJson("/api/v1/hr/candidates/{$applicant->id}/hire")
            ->assertCreated()
            ->assertJsonPath('data.candidate_id', $applicant->id)
            ->assertJsonPath('data.first_name', 'Amine')
            ->assertJsonPath('data.payroll_eligible', true);

        /** @var Employee $employee */
        $employee = Employee::query()->where('candidate_id', $applicant->id)->firstOrFail();
        $this->assertSame($applicant->email, $employee->email);
        $this->assertNotNull($employee->contract_start, 'contract_start posé dès l\'embauche (paie possible).');

        Event::assertDispatched(CandidateHired::class);
    }

    public function test_hire_blocked_when_mandatory_onboarding_incomplete(): void
    {
        // Pas de completeOnboarding() : un step obligatoire est absent/incomplet.
        $applicant = $this->makeApplicant();
        OnboardingStep::query()->create([
            'company_id' => $this->company->id,
            'step_key' => 'payroll_setup',
            'title' => 'Paramétrage paie',
            'status' => 'pending',
            'required' => true,
            'order' => 1,
        ]);
        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson("/api/v1/hr/candidates/{$applicant->id}/hire")
            ->assertStatus(409);

        $this->assertSame(0, Employee::query()->where('candidate_id', $applicant->id)->count());
    }

    public function test_hire_blocked_when_candidate_already_hired(): void
    {
        $this->completeOnboarding();
        $applicant = $this->makeApplicant('hired');
        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson("/api/v1/hr/candidates/{$applicant->id}/hire")
            ->assertStatus(422);

        $this->assertSame(0, Employee::query()->where('candidate_id', $applicant->id)->count());
    }

    public function test_hire_unknown_candidate_returns_404(): void
    {
        Sanctum::actingAs($this->manager, ['*']);

        $this->postJson('/api/v1/hr/candidates/999999/hire')
            ->assertNotFound();
    }

    public function test_hired_candidate_produces_a_pay_slip_do_d(): void
    {
        $this->completeOnboarding();
        $applicant = $this->makeApplicant();
        Sanctum::actingAs($this->manager, ['*']);

        // 1. Embauche via le parcours candidat → employé.
        $this->postJson("/api/v1/hr/candidates/{$applicant->id}/hire")->assertCreated();

        // 2. Grille salariale active (pattern CriticalFunnelPayrollE2ETest).
        SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille DoD 5261',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        // 3. Run de paie réel : draft → calculated → validated.
        $runResponse = $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'notes' => 'Recrutement simulé DoD #5261',
        ]);
        $runResponse->assertCreated()->assertJsonPath('data.status', PayrollRun::STATUS_DRAFT);
        /** @var int $runId */
        $runId = $runResponse->json('data.id');

        $this->postJson("/api/v1/payroll-runs/{$runId}/calculate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_CALCULATED);

        $this->postJson("/api/v1/payroll-runs/{$runId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_VALIDATED);

        // 4. Bulletin réellement généré pour le candidat embauché.
        /** @var PaySlip $slip */
        $slip = PaySlip::query()
            ->where('payroll_run_id', $runId)
            ->where('employee_id', $this->employeeIdFromCandidate($applicant))
            ->firstOrFail();

        $pdf = $this->getJson("/api/v1/me/pay-slips/{$slip->id}/pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', substr((string) $pdf->getContent(), 0, 4));
    }

    private function employeeIdFromCandidate(Applicant $applicant): int
    {
        /** @var Employee $employee */
        $employee = Employee::query()->where('candidate_id', $applicant->id)->firstOrFail();

        return $employee->id;
    }
}
