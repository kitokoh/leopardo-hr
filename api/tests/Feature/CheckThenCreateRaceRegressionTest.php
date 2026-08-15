<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use App\Modules\Payroll\Domain\Models\Commission;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Modules\Payroll\Infrastructure\Services\CommissionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #3811 (audit 360° 2026-08-15) — races check-then-create : les gardes
 * `exists()` ne sont pas atomiques ; l'index unique + catch 23505 rendent les
 * insertions concurrentes idempotentes (422 ou skip propre) au lieu d'un
 * 500 SQL brut.
 */
class CheckThenCreateRaceRegressionTest extends TestCase
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

    public function test_commission_unique_index_rejects_duplicate_payment_id(): void
    {
        $company = Company::factory()->create();
        $partner = Partner::create([
            'user_id' => 1,
            'referral_code' => 'race-test-'.Str::random(6),
        ]);

        // L'index unique de la migration #3811 n'existe pas dans le schéma de
        // test MVP — on le matérialise pour valider le comportement attendu.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS commissions_payment_id_unique ON commissions (payment_id)');

        Commission::create([
            'partner_id' => $partner->id,
            'company_id' => $company->id,
            'payment_id' => 42,
            'amount' => 1000,
            'net_amount' => 900,
            'currency' => 'DZD',
            'applied_rate' => 1000,
            'status' => 'pending',
        ]);

        // Seconde insertion du même payment_id : 23505, pas de doublon.
        try {
            Commission::create([
                'partner_id' => $partner->id,
                'company_id' => $company->id,
                'payment_id' => 42,
                'amount' => 1000,
                'net_amount' => 900,
                'currency' => 'DZD',
                'applied_rate' => 1000,
                'status' => 'pending',
            ]);
            $this->fail('L\'index unique commissions_payment_id_unique aurait dû lever 23505.');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->getCode());
        }
    }

    public function test_commission_service_returns_null_when_already_recorded(): void
    {
        $company = Company::factory()->create();
        $partner = Partner::create([
            'user_id' => 1,
            'referral_code' => 'race-test-'.Str::random(6),
        ]);
        $payment = Payment::create([
            'invoice_id' => 1,
            'company_id' => $company->id,
            'amount' => 100000,
            'currency' => 'DZD',
            'status' => 'completed',
        ]);

        Commission::create([
            'partner_id' => $partner->id,
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'amount' => 1000,
            'net_amount' => 900,
            'currency' => 'DZD',
            'applied_rate' => 1000,
            'status' => 'pending',
        ]);

        // Idempotence préservée : la commission existe déjà → null, pas de 500.
        $this->assertNull((new CommissionService)->recordCommissionForPayment($payment));
    }

    public function test_self_enroll_returns_422_when_already_enrolled(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $course = TrainingCourse::create([
            'company_id' => $company->id,
            'title' => 'Formation test',
        ]);
        $session = TrainingSession::create([
            'training_course_id' => $course->id,
            'company_id' => $company->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'status' => 'scheduled',
        ]);

        TrainingEnrollment::create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'status' => 'enrolled',
        ]);

        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/me/trainings/{$session->id}/enroll")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Already enrolled in this session.');
    }
}
