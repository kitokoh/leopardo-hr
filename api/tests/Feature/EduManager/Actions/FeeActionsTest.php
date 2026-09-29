<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Actions\CreateEduFeeChargeAction;
use App\Modules\EduManager\Application\Actions\RecordEduFeePaymentAction;
use App\Modules\EduManager\Application\Actions\WaiveEduFeeChargeAction;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-024a (#8212) — Actions du cas d'usage « facturation scolaire » :
 * création de charge (montant hérité du type si omis), encaissement partiel
 * puis soldant, abandon de solde.
 */
class FeeActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private EduAcademicYear $yearA;

    private EduStudent $studentA;

    private EduFeeType $feeTypeA;

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

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;

        /** @var EduFeeType $feeTypeA */
        $feeTypeA = EduFeeType::query()->create([
            'company_id' => $companyA->id,
            'code' => 'SCOL-STD',
            'label' => 'Frais de scolarité',
            'amount' => 5000,
            'currency' => 'DZD',
            'billing_frequency' => 'monthly',
            'is_active' => true,
        ]);
        $this->feeTypeA = $feeTypeA;
    }

    public function test_create_charge_defaults_amount_and_currency_from_fee_type(): void
    {
        $charge = app(CreateEduFeeChargeAction::class)->execute($this->principalA, [
            'student_id' => $this->studentA->getKey(),
            'fee_type_id' => $this->feeTypeA->getKey(),
            'academic_year_id' => $this->yearA->getKey(),
            'due_date' => '2026-10-01',
        ]);

        $this->assertSame($this->companyA->getKey(), $charge->getAttribute('company_id'));
        $this->assertSame(EduFeeCharge::STATUS_PENDING, $charge->status);
        $this->assertEquals(5000.0, $charge->amount);
        $this->assertSame('DZD', $charge->currency);
    }

    public function test_record_payment_partial_then_settled(): void
    {
        $charge = app(CreateEduFeeChargeAction::class)->execute($this->principalA, [
            'student_id' => $this->studentA->getKey(),
            'fee_type_id' => $this->feeTypeA->getKey(),
            'academic_year_id' => $this->yearA->getKey(),
        ]);

        $action = app(RecordEduFeePaymentAction::class);

        ['payment' => $first, 'charge' => $partial] = $action->execute($this->principalA, $charge, [
            'amount' => 2000,
            'method' => EduFeePayment::METHOD_CASH,
        ]);

        $this->assertInstanceOf(EduFeePayment::class, $first);
        $this->assertSame(EduFeeCharge::STATUS_PARTIAL, $partial->status);

        ['charge' => $settled] = $action->execute($this->principalA, $partial, [
            'amount' => 3000,
            'method' => EduFeePayment::METHOD_TRANSFER,
        ]);

        $this->assertSame(EduFeeCharge::STATUS_PAID, $settled->status);
        $this->assertSame(2, EduFeePayment::query()
            ->where('fee_charge_id', $charge->getAttribute('id'))
            ->count());
    }

    public function test_waive_charge_marks_waived(): void
    {
        $charge = app(CreateEduFeeChargeAction::class)->execute($this->principalA, [
            'student_id' => $this->studentA->getKey(),
            'fee_type_id' => $this->feeTypeA->getKey(),
            'academic_year_id' => $this->yearA->getKey(),
        ]);

        $waived = app(WaiveEduFeeChargeAction::class)->execute($this->principalA, $charge);

        $this->assertSame(EduFeeCharge::STATUS_WAIVED, $waived->status);
    }
}
