<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAccountingEntry;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduOutboxEvent;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5832 (EDU-016) — frais scolaires et contrat Accounting.
 *
 * Verrouille : catalogue idempotent par code, facturation idempotente
 * (external_id), encaissements sans surdébit, transitions de statut,
 * écritures comptables équilibrées régénérées sans doublon, événements
 * outbox versionnés, RBAC direction, isolation cross-tenant.
 */
class EduFeeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduAcademicYear $yearA;

    private EduStudent $studentA;

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
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    private function createFeeType(array $overrides = []): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-types', array_merge([
            'code' => 'TUITION',
            'label' => 'Scolarité annuelle',
            'amount' => 50000,
            'currency' => 'DZD',
            'billing_frequency' => 'once',
        ], $overrides));

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    public function test_fee_type_crud_and_rbac(): void
    {
        // Employé lambda : 403.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/fee-types')->assertStatus(403);
        $this->postJson($this->baseUrl().'/fee-types', [])->assertStatus(403);

        // Direction : création + lecture.
        $feeTypeId = $this->createFeeType();
        $this->getJson($this->baseUrl().'/fee-types')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'TUITION')
            ->assertJsonPath('data.0.amount', 50000);

        // Code dupliqué (même tenant) : 422.
        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl().'/fee-types', [
            'code' => 'TUITION',
            'label' => 'Doublon',
            'amount' => 100,
            'currency' => 'DZD',
            'billing_frequency' => 'once',
        ])->assertStatus(422);

        // Unauthenticated : 401.
        // `Sanctum::actingAs()` persiste pour tout le test : sans purge des
        // gardes, l'appel ci-dessous était authentifié et l'assertion 401 ne
        // pouvait pas passer (elle n'était donc jamais atteinte, le test
        // échouant avant). Même helper que
        // `tests/Feature/Travel/TravelAdvertDestroyTest.php`.
        Auth::forgetGuards();
        $this->getJson($this->baseUrl().'/fee-types')->assertStatus(401);
    }

    public function test_charge_is_idempotent_on_external_id(): void
    {
        $feeTypeId = $this->createFeeType();
        Sanctum::actingAs($this->principalA);

        $payload = [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'external_id' => 'ext-charge-1',
        ];

        $first = $this->postJson($this->baseUrl().'/fee-charges', $payload)->assertStatus(201);
        $second = $this->postJson($this->baseUrl().'/fee-charges', $payload)->assertStatus(201);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, EduFeeCharge::query()->where('company_id', $this->companyA->id)->count());
        // Montant par défaut = tarif du type.
        $this->assertSame(50000.0, (float) $first->json('data.amount'));
        $this->assertSame('pending', $first->json('data.status'));
    }

    public function test_charge_writes_balanced_accounting_entries_and_outbox_event(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-entries');

        $entries = EduAccountingEntry::query()
            ->where('company_id', $this->companyA->id)
            ->where('source_type', EduAccountingEntry::SOURCE_FEE_CHARGE)
            ->where('source_id', $chargeId)
            ->get();

        $this->assertSame(2, $entries->count());
        $this->assertSame(50000.0, (float) $entries->sum('debit'));
        $this->assertSame(50000.0, (float) $entries->sum('credit'));

        $this->assertSame(1, EduOutboxEvent::query()
            ->where('company_id', $this->companyA->id)
            ->where('event_type', EduFeeService::EVENT_CHARGE_CREATED)
            ->count());
    }

    public function test_payment_transitions_and_no_overpayment(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-pay');

        Sanctum::actingAs($this->principalA);

        // 30 000 sur 50 000 → partial.
        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 30000,
            'method' => 'cash',
            'external_id' => 'ext-pay-1',
        ])->assertStatus(201)
            ->assertJsonPath('data.charge.status', 'partial');

        // 30 000 en plus → surdébit 422.
        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 30000,
            'method' => 'cash',
            'external_id' => 'ext-pay-2',
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_FEE_OVERPAYMENT');

        // 20 000 → paid.
        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 20000,
            'method' => 'transfer',
            'external_id' => 'ext-pay-3',
        ])->assertStatus(201)->assertJsonPath('data.charge.status', 'paid');

        // Rejeu du premier paiement → idempotent (200/201, même id).
        $replay = $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 30000,
            'method' => 'cash',
            'external_id' => 'ext-pay-1',
        ])->assertStatus(201);

        $this->assertSame(
            EduFeePayment::query()->where('company_id', $this->companyA->id)->where('external_id', 'ext-pay-1')->first()?->getAttribute('id'),
            $replay->json('data.payment.id')
        );

        // Paiement sur charge soldée : 422 terminal.
        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 100,
            'method' => 'cash',
            'external_id' => 'ext-pay-4',
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_FEE_TERMINAL');
    }

    public function test_payment_writes_balanced_entries(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-bal');
        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 20000,
            'method' => 'cash',
            'external_id' => 'ext-pay-bal',
        ])->assertStatus(201);

        $payment = EduFeePayment::query()->where('external_id', 'ext-pay-bal')->firstOrFail();
        $entries = EduAccountingEntry::query()
            ->where('company_id', $this->companyA->id)
            ->where('source_type', EduAccountingEntry::SOURCE_FEE_PAYMENT)
            ->where('source_id', (int) $payment->getAttribute('id'))
            ->get();

        $this->assertSame(2, $entries->count());
        $this->assertSame(20000.0, (float) $entries->sum('debit'));
        $this->assertSame(20000.0, (float) $entries->sum('credit'));
        $this->assertSame('531000', $entries->first()->account_code);
    }

    public function test_waive_sets_status_and_writes_remaining_receivable(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-waive');
        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/waive")
            ->assertOk()
            ->assertJsonPath('data.status', 'waived');

        $waiver = EduAccountingEntry::query()
            ->where('company_id', $this->companyA->id)
            ->where('source_type', EduAccountingEntry::SOURCE_FEE_WAIVER)
            ->where('source_id', $chargeId)
            ->get();

        $this->assertSame(2, $waiver->count());
        $this->assertSame(50000.0, (float) $waiver->sum('debit'));

        $this->assertSame(1, EduOutboxEvent::query()
            ->where('company_id', $this->companyA->id)
            ->where('event_type', EduFeeService::EVENT_CHARGE_WAIVED)
            ->count());
    }

    public function test_entries_are_reconciliable_and_isolated(): void
    {
        $feeTypeId = $this->createFeeType();
        $this->createCharge($feeTypeId, 'ext-charge-iso');
        Sanctum::actingAs($this->principalA);

        $this->getJson($this->baseUrl().'/fee-accounting-entries')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        // Isolation : un manager du tenant B ne voit rien (404 sur charge A).
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $this->getJson($this->baseUrl().'/fee-accounting-entries')->assertOk()->assertJsonPath('meta.total', 0);

        $chargeId = (int) EduFeeCharge::query()->where('company_id', $this->companyA->id)->value('id');
        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 100,
            'method' => 'cash',
            'external_id' => 'ext-pay-x-tenant',
        ])->assertStatus(404);
    }

    /**
     * #9790 — la liste des charges expose le solde restant et se filtre.
     *
     * Sans `index`, l'écran de facturation n'avait aucun moyen de lister les
     * charges : seules les routes d'écriture existaient. Le solde est calculé
     * en SQL (`withSum`) — on vérifie qu'il est juste après un encaissement
     * partiel, et que les filtres ne fuient pas hors du tenant.
     */
    public function test_charge_index_exposes_balance_and_filters(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-list');

        Sanctum::actingAs($this->principalA);

        // Avant tout encaissement : rien de payé, tout restant dû.
        $this->getJson($this->baseUrl().'/fee-charges')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', 50000)
            ->assertJsonPath('data.0.paid_amount', 0)
            ->assertJsonPath('data.0.remaining_amount', 50000)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.student.display_name', 'Lina Benali')
            ->assertJsonPath('data.0.fee_type.code', 'TUITION');

        // Encaissement partiel : le solde suit.
        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 20000,
            'method' => 'cash',
            'external_id' => 'ext-pay-list',
        ])->assertStatus(201);

        $this->getJson($this->baseUrl().'/fee-charges')
            ->assertOk()
            ->assertJsonPath('data.0.paid_amount', 20000)
            ->assertJsonPath('data.0.remaining_amount', 30000)
            ->assertJsonPath('data.0.status', 'partial');

        // Filtres : statut, élève, type de frais.
        $this->getJson($this->baseUrl().'/fee-charges?status=partial')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson($this->baseUrl().'/fee-charges?status=paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson($this->baseUrl().'/fee-charges?student_id='.(int) $this->studentA->getAttribute('id'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson($this->baseUrl().'/fee-charges?student_id=999999')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson($this->baseUrl().'/fee-charges?fee_type_id='.$feeTypeId)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // RBAC : employé lambda 403, non authentifié 401.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/fee-charges')->assertStatus(403);

        Auth::forgetGuards();
        $this->getJson($this->baseUrl().'/fee-charges')->assertStatus(401);
    }

    /**
     * #9790 — le détail d'une charge expose ses encaissements et reste borné
     * au tenant (404, jamais 403 : on ne révèle pas l'existence d'une charge
     * d'un autre tenant).
     */
    public function test_charge_show_exposes_payments_and_isolates_tenant(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-show');

        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 15000,
            'method' => 'transfer',
            'reference' => 'VIR-001',
            'external_id' => 'ext-pay-show',
        ])->assertStatus(201);

        $this->getJson($this->baseUrl()."/fee-charges/{$chargeId}")
            ->assertOk()
            ->assertJsonPath('data.id', $chargeId)
            ->assertJsonPath('data.paid_amount', 15000)
            ->assertJsonPath('data.remaining_amount', 35000)
            ->assertJsonPath('data.payments.0.amount', 15000)
            ->assertJsonPath('data.payments.0.method', 'transfer')
            ->assertJsonPath('data.payments.0.reference', 'VIR-001');

        // Charge inexistante : 404.
        $this->getJson($this->baseUrl().'/fee-charges/999999')->assertStatus(404);

        // Isolation : un manager du tenant B ne voit pas la charge A.
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $this->getJson($this->baseUrl()."/fee-charges/{$chargeId}")->assertStatus(404);
        $this->getJson($this->baseUrl().'/fee-charges')->assertOk()->assertJsonPath('meta.total', 0);
    }

    /**
     * #9790 — journal des encaissements : les paiements n'étaient lisibles
     * qu'en détail d'une charge, jamais en travers du guichet.
     */
    public function test_fee_payments_journal_lists_and_filters(): void
    {
        $feeTypeId = $this->createFeeType();
        $chargeId = $this->createCharge($feeTypeId, 'ext-charge-journal');

        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 10000,
            'method' => 'cash',
            'external_id' => 'ext-pay-j1',
        ])->assertStatus(201);

        $this->postJson($this->baseUrl()."/fee-charges/{$chargeId}/payments", [
            'amount' => 5000,
            'method' => 'card',
            'external_id' => 'ext-pay-j2',
        ])->assertStatus(201);

        $this->getJson($this->baseUrl().'/fee-payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.student_id', (int) $this->studentA->getAttribute('id'));

        // Filtres : charge, méthode, élève.
        $this->getJson($this->baseUrl().'/fee-payments?fee_charge_id='.$chargeId)
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson($this->baseUrl().'/fee-payments?method=card')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', 5000);

        $this->getJson($this->baseUrl().'/fee-payments?student_id='.(int) $this->studentA->getAttribute('id'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson($this->baseUrl().'/fee-payments?student_id=999999')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        // RBAC : employé lambda 403.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/fee-payments')->assertStatus(403);

        // Isolation : le tenant B ne voit aucun encaissement.
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);
        $this->getJson($this->baseUrl().'/fee-payments')->assertOk()->assertJsonPath('meta.total', 0);
    }

    private function createCharge(int $feeTypeId, string $externalId): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-charges', [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'external_id' => $externalId,
        ]);

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }
}
