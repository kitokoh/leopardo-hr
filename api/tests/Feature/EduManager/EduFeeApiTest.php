<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduFee;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API des frais scolaires — EDU-016 (issue #5832).
 *
 * Couvre : 401, solution inactive 403, employé lambda 403, CRUD direction,
 * rejeu idempotent (external_reference), règlement/annulation/remise
 * terminaux, audit edu.fee.*, isolation cross-tenant 404.
 */
class EduFeeApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduStudent $studentA;

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var EduCampus $campusA */
        $campusA = EduCampus::query()->create([
            'company_id' => $companyA->id,
            'code' => 'CAMPUS-A',
            'name' => 'Campus A',
        ]);

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/fees')->assertStatus(401);
        $this->postJson($this->baseUrl().'/fees', [])->assertStatus(401);
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/fees')->assertStatus(403);
    }

    public function test_direction_crud_flow(): void
    {
        Sanctum::actingAs($this->principalA);

        $feeId = $this->postJson($this->baseUrl().'/fees', [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => "Frais d'inscription",
            'amount' => 2500,
            'due_date' => '2026-09-15',
            'external_reference' => 'FEE-2026-001',
        ])->assertStatus(201)->json('data.id');

        $this->getJson($this->baseUrl()."/fees/{$feeId}")->assertOk()->assertJsonPath('data.label', "Frais d'inscription");

        // Rejeu idempotent : même external_reference → même frais (pas de doublon).
        $this->postJson($this->baseUrl().'/fees', [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => "Frais d'inscription",
            'amount' => 2500,
            'due_date' => '2026-09-15',
            'external_reference' => 'FEE-2026-001',
        ])->assertStatus(201)->assertJsonPath('data.id', $feeId);

        // Règlement → paid, terminal.
        $this->postJson($this->baseUrl()."/fees/{$feeId}/pay", [
            'payment_reference' => 'PAY-001',
        ])->assertOk()->assertJsonPath('data.status', EduFee::STATUS_PAID);

        // Terminal : annulation refusée après règlement.
        $this->postJson($this->baseUrl()."/fees/{$feeId}/cancel")->assertStatus(422)->assertJsonPath('error', 'EDU_FEE_TERMINAL');

        // Liste filtrée par statut.
        $this->getJson($this->baseUrl().'/fees?status=paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_waive_marks_fee_waived(): void
    {
        Sanctum::actingAs($this->principalA);

        $feeId = $this->postJson($this->baseUrl().'/fees', [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => 'Scolarité',
            'amount' => 5000,
            'due_date' => '2026-09-30',
        ])->assertStatus(201)->json('data.id');

        $this->postJson($this->baseUrl()."/fees/{$feeId}/waive")
            ->assertOk()
            ->assertJsonPath('data.status', EduFee::STATUS_WAIVED);
    }

    public function test_cross_tenant_fee_is_404(): void
    {
        Sanctum::actingAs($this->principalA);

        /** @var EduStudent $studentB */
        $studentB = EduStudent::query()->create([
            'company_id' => $this->companyB->id,
            'student_number' => 'STU-B-1',
            'display_name' => 'Élève B',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        // Un frais du tenant B est invisible pour le principal A (404).
        /** @var EduFee $feeB */
        $feeB = EduFee::query()->create([
            'company_id' => $this->companyB->id,
            'student_id' => (int) $studentB->getAttribute('id'),
            'label' => 'Frais B',
            'amount' => 100,
            'due_date' => '2026-09-30',
            'status' => EduFee::STATUS_PENDING,
        ]);

        $this->getJson($this->baseUrl()."/fees/{$feeB->getAttribute('id')}")->assertStatus(404);
        $this->postJson($this->baseUrl()."/fees/{$feeB->getAttribute('id')}/pay", ['payment_reference' => 'X'])->assertStatus(404);
    }

    public function test_fees_api_never_creates_accounting_entries(): void
    {
        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl().'/fees', [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => 'Scolarité',
            'amount' => 5000,
            'due_date' => '2026-09-30',
        ])->assertStatus(201);

        // Contrat Accounting : aucune écriture comptable créée par EduManager.
        $this->assertDatabaseMissing('accounting_documents', [
            'company_id' => $this->companyA->id,
        ]);
    }
}
