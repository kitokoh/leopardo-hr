<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9793 — reçu PDF d'un encaissement en lot, et lot depuis la fiche d'un élève.
 *
 * Deux garanties verrouillées ici :
 *
 * 1. **Le reçu est réimprimable.** Un lot n'était identifiable que par le
 *    préfixe de `external_id`, et seulement quand l'appelant en fournissait un :
 *    deux dépôts anonymes étaient indistinguables. `batch_id` matérialise le
 *    lot, y compris sans `external_id`.
 * 2. **Le reçu ne fuit pas.** Un lot d'un autre tenant → 404, jamais un PDF
 *    vide ni les montants d'une autre école.
 */
class EduFeeBatchReceiptTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $principalA;

    private Employee $principalB;

    private EduAcademicYear $yearA;

    /** @var list<EduStudent> */
    private array $studentsA = [];

    protected function setUp(): void
    {
        parent::setUp();

        // #9859 — les deux sociétés ne servaient qu'à créer `principalA` et
        // `principalB` (lus par les tests) : les garder comme propriétés les
        // rendait écrites-jamais-lues (PHPStan `property.onlyWritten`).
        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalB = $principalB;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        foreach (['Lina Benali', 'Yacine Haddad'] as $i => $name) {
            /** @var EduStudent $student */
            $student = EduStudent::query()->create([
                'company_id' => $companyA->id,
                'student_number' => 'STU-000'.($i + 1),
                'display_name' => $name,
                'status' => EduStudent::STATUS_ACTIVE,
            ]);
            $this->studentsA[] = $student;
        }
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    private function createFeeType(): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-types', [
            'code' => 'TUITION',
            'label' => 'Scolarité annuelle',
            'amount' => 50000,
            'currency' => 'DZD',
            'billing_frequency' => 'once',
        ]);

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    private function createCharge(int $feeTypeId, int $studentIndex, string $externalId): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-charges', [
            'student_id' => (int) $this->studentsA[$studentIndex]->getAttribute('id'),
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'external_id' => $externalId,
        ]);

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    /**
     * Le lot expose un `batch_id` et le reçu se télécharge en PDF.
     */
    public function test_batch_returns_batch_id_and_receipt_is_downloadable(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-receipt-1');
        $c2 = $this->createCharge($feeTypeId, 1, 'ext-receipt-2');

        Sanctum::actingAs($this->principalA);

        $batch = $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'cash',
            'reference' => 'BORD-2026-09',
            'external_id' => 'lot-recu-1',
            'lines' => [
                ['charge_id' => $c1, 'amount' => 50000],
                ['charge_id' => $c2, 'amount' => 20000],
            ],
        ]);

        $batch->assertStatus(201);
        $this->assertSame('lot-recu-1', $batch->json('summary.batch_id'));

        // Chaque encaissement porte le lot : c'est ce qui rend le reçu
        // réimprimable, indépendamment de `external_id`.
        $this->assertSame(2, EduFeePayment::query()->where('batch_id', 'lot-recu-1')->count());

        $receipt = $this->get($this->baseUrl().'/fee-payments/batches/lot-recu-1/receipt.pdf');

        $receipt->assertStatus(200);
        $this->assertSame('application/pdf', $receipt->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment;',
            (string) $receipt->headers->get('Content-Disposition'),
        );
        $this->assertStringStartsWith('%PDF', (string) $receipt->getContent());
    }

    /**
     * Un lot SANS `external_id` reste identifiable : un UUID est généré, sinon
     * deux dépôts anonymes seraient indistinguables et le reçu introuvable.
     */
    public function test_batch_without_external_id_still_gets_a_batch_id(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-anon-1');

        Sanctum::actingAs($this->principalA);

        $batch = $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'transfer',
            'lines' => [['charge_id' => $c1, 'amount' => 10000]],
        ]);

        $batch->assertStatus(201);

        $batchId = (string) $batch->json('summary.batch_id');
        $this->assertNotSame('', $batchId);

        $this->get($this->baseUrl().'/fee-payments/batches/'.$batchId.'/receipt.pdf')
            ->assertStatus(200);
    }

    /**
     * Le reçu d'un lot d'une autre école → 404 : on ne révèle ni son existence
     * ni ses montants.
     */
    public function test_receipt_of_another_tenant_batch_is_not_found(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-cross-1');

        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl().'/fee-charges/batch-payments', [
            'method' => 'cash',
            'external_id' => 'lot-tenant-a',
            'lines' => [['charge_id' => $c1, 'amount' => 10000]],
        ])->assertStatus(201);

        Sanctum::actingAs($this->principalB);

        $this->get($this->baseUrl().'/fee-payments/batches/lot-tenant-a/receipt.pdf')
            ->assertStatus(404);
    }

    /**
     * Un lot inexistant → 404, jamais un PDF vide qui ferait croire à un
     * encaissement nul.
     */
    public function test_unknown_batch_receipt_is_not_found(): void
    {
        Sanctum::actingAs($this->principalA);

        $this->get($this->baseUrl().'/fee-payments/batches/inexistant/receipt.pdf')
            ->assertStatus(404);
    }

    /**
     * Lot depuis la fiche d'un élève : toutes ses charges encaissables, et
     * seulement les siennes.
     */
    public function test_student_fee_charges_lists_only_that_student_open_charges(): void
    {
        $feeTypeId = $this->createFeeType();
        $mine = $this->createCharge($feeTypeId, 0, 'ext-student-1');
        $other = $this->createCharge($feeTypeId, 1, 'ext-student-2');

        // Une charge soldée ne doit pas être proposée : le lot la refuserait.
        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl().'/fee-charges/'.$mine.'/payments', [
            'amount' => 50000,
            'method' => 'cash',
        ])->assertStatus(201);

        $studentId = (int) $this->studentsA[0]->getAttribute('id');

        $response = $this->getJson($this->baseUrl().'/students/'.$studentId.'/fee-charges');

        $response->assertStatus(200)
            ->assertJsonPath('data.student.id', $studentId)
            ->assertJsonPath('meta.charges', 0);

        // Avec `include_terminal`, la charge soldée réapparaît — mais jamais
        // celle de l'autre élève.
        $withTerminal = $this->getJson(
            $this->baseUrl().'/students/'.$studentId.'/fee-charges?include_terminal=1',
        );

        $withTerminal->assertStatus(200)->assertJsonPath('meta.charges', 1);
        $this->assertSame($mine, (int) $withTerminal->json('data.charges.0.id'));
        $this->assertNotSame($other, (int) $withTerminal->json('data.charges.0.id'));
    }

    /**
     * Un élève d'une autre école → 404 (aucune fuite d'existence).
     */
    public function test_student_fee_charges_of_another_tenant_is_not_found(): void
    {
        $studentId = (int) $this->studentsA[0]->getAttribute('id');

        Sanctum::actingAs($this->principalB);

        $this->getJson($this->baseUrl().'/students/'.$studentId.'/fee-charges')
            ->assertStatus(404);
    }
}
