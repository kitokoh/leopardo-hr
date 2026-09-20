<?php

declare(strict_types=1);

namespace Tests\Feature\Pharmacy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyStockService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Ordonnances, prescripteurs et ordonnancier — PHARMA-006 (#7803).
 *
 * Couvre : CRUD prescripteurs/ordonnances (référence unique/tenant, PII
 * patients isolées), vente d'un produit contrôlé sans ordonnance refusée,
 * ordonnancier dérivé des mouvements sale/return des produits is_controlled
 * (void en contre-passation, jamais d'effacement), accès manager only,
 * export CSV borné à la période, solution inactive 403.
 */
class PharmacyPrescriptionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyB;

    private Employee $managerA;

    private Employee $lambdaA;

    private Employee $managerB;

    private PharmacyProduct $controlled;

    private PharmacyPrescriber $prescriberA;

    private PharmacyPrescription $prescriptionA;

    private function baseUrl(): string
    {
        return '/api/v1/pharmacy';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['pharmacy' => true],
        ]);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['pharmacy' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerA = $managerA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerB = $managerB;

        /** @var PharmacyProduct $controlled */
        $controlled = PharmacyProduct::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'name' => 'Morphine 10mg',
            'sale_price' => '900.00',
            'is_controlled' => true,
        ]);
        $this->controlled = $controlled;

        /** @var PharmacyPrescriber $prescriberA */
        $prescriberA = PharmacyPrescriber::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'full_name' => 'Dr Lina Brahimi',
            'registration_number' => 'ORD-16-1234',
            'specialty' => 'Médecine générale',
        ]);
        $this->prescriberA = $prescriberA;

        /** @var PharmacyPrescription $prescriptionA */
        $prescriptionA = PharmacyPrescription::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'prescriber_id' => $prescriberA->id,
            'patient_name' => 'Sofiane Merbah',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-2026-001',
        ]);
        $this->prescriptionA = $prescriptionA;

        app(PharmacyStockService::class)->receive(
            (string) $companyA->id,
            (int) $controlled->id,
            'LOT-M1',
            Carbon::today()->addYear()->toDateString(),
            50,
            '500.00',
        );
    }

    public function test_inactive_solution_gets_403(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson($this->baseUrl().'/prescribers')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/prescriptions')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/controlled-register')->assertStatus(403)->assertJsonPath('error', 'PHARMACY_SOLUTION_INACTIVE');
    }

    public function test_prescriber_crud_and_rbac(): void
    {
        // Écriture réservée au manager.
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/prescribers', ['full_name' => 'Dr Interdit'])->assertStatus(403);

        Sanctum::actingAs($this->managerA);
        $prescriberId = $this->postJson($this->baseUrl().'/prescribers', [
            'full_name' => 'Dr Yacine Hamdi',
            'registration_number' => 'ORD-16-9999',
            'specialty' => 'Cardiologie',
        ])->assertStatus(201)->json('data.id');

        $this->putJson($this->baseUrl().'/prescribers/'.$prescriberId, ['status' => 'archived'])
            ->assertStatus(200)->assertJsonPath('data.status', 'archived');

        $this->getJson($this->baseUrl().'/prescribers?search=hamdi')
            ->assertStatus(200)->assertJsonPath('meta.total', 1);

        // Cross-tenant → 404.
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/prescribers/'.$prescriberId)->assertStatus(404);
    }

    public function test_prescription_crud_reference_unique_and_pii_isolation(): void
    {
        Sanctum::actingAs($this->lambdaA);

        // Le comptoir peut saisir une ordonnance.
        $prescriptionId = $this->postJson($this->baseUrl().'/prescriptions', [
            'prescriber_id' => $this->prescriberA->id,
            'patient_name' => 'Amel Zerrouki',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-2026-002',
        ])->assertStatus(201)
            ->assertJsonPath('data.prescriber_name', 'Dr Lina Brahimi')
            ->json('data.id');

        // Référence dupliquée dans le tenant → 422.
        $this->postJson($this->baseUrl().'/prescriptions', [
            'prescriber_id' => $this->prescriberA->id,
            'patient_name' => 'Autre Patient',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-2026-002',
        ])->assertStatus(422)->assertJsonValidationErrors(['reference']);

        // Prescripteur d'un autre tenant → 404.
        Sanctum::actingAs($this->managerB);
        $this->postJson($this->baseUrl().'/prescriptions', [
            'prescriber_id' => $this->prescriberA->id,
            'patient_name' => 'Fuite',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-B-001',
        ])->assertStatus(404);

        // La même référence est autorisée dans un autre tenant.
        /** @var PharmacyPrescriber $prescriberB */
        $prescriberB = PharmacyPrescriber::withoutGlobalScopes()->create([
            'company_id' => $this->companyB->id,
            'full_name' => 'Dr Rachid Alaoui',
        ]);
        $this->postJson($this->baseUrl().'/prescriptions', [
            'prescriber_id' => $prescriberB->id,
            'patient_name' => 'Patient B',
            'prescribed_at' => Carbon::today()->toDateString(),
            'reference' => 'ORD-2026-002',
        ])->assertStatus(201);

        // PII du tenant A invisibles pour B.
        $this->getJson($this->baseUrl().'/prescriptions/'.$prescriptionId)->assertStatus(404);
        $this->getJson($this->baseUrl().'/prescriptions?search=zerrouki')
            ->assertStatus(200)->assertJsonPath('meta.total', 0);
    }

    public function test_controlled_register_derives_from_movements_with_counter_passation(): void
    {
        Sanctum::actingAs($this->lambdaA);

        // Vente d'un produit contrôlé SANS ordonnance → refusée.
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'lines' => [['product_id' => $this->controlled->id, 'quantity' => 2]],
        ])->assertStatus(422)->assertJsonPath('error', 'PHARMACY_PRESCRIPTION_REQUIRED');

        // Vente valide avec ordonnance.
        $saleId = $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'prescription_id' => $this->prescriptionA->id,
            'lines' => [['product_id' => $this->controlled->id, 'quantity' => 2]],
        ])->assertStatus(201)->json('data.id');

        // L'ordonnancier est réservé au manager.
        $this->getJson($this->baseUrl().'/controlled-register')->assertStatus(403);

        Sanctum::actingAs($this->managerA);
        $this->getJson($this->baseUrl().'/controlled-register')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.direction', 'dispense')
            ->assertJsonPath('data.0.product_name', 'Morphine 10mg')
            ->assertJsonPath('data.0.batch_number', 'LOT-M1')
            ->assertJsonPath('data.0.quantity', 2)
            ->assertJsonPath('data.0.prescription_reference', 'ORD-2026-001')
            ->assertJsonPath('data.0.prescriber_name', 'Dr Lina Brahimi')
            ->assertJsonPath('data.0.patient_name', 'Sofiane Merbah');

        // Void → contre-passation `return` (l'entrée dispense est CONSERVÉE).
        $this->postJson($this->baseUrl().'/sales/'.$saleId.'/void', ['reason' => 'erreur'])->assertStatus(200);

        $this->getJson($this->baseUrl().'/controlled-register')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.direction', 'dispense')
            ->assertJsonPath('data.1.direction', 'return')
            ->assertJsonPath('data.1.quantity', 2);

        // Tenant B : registre vide (aucune fuite PII).
        Sanctum::actingAs($this->managerB);
        $this->getJson($this->baseUrl().'/controlled-register')
            ->assertStatus(200)->assertJsonPath('meta.total', 0);
    }

    public function test_controlled_register_csv_export_bounded_by_period(): void
    {
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl().'/sales', [
            'payment_method' => 'cash',
            'prescription_id' => $this->prescriptionA->id,
            'lines' => [['product_id' => $this->controlled->id, 'quantity' => 3]],
        ])->assertStatus(201);

        Sanctum::actingAs($this->managerA);

        $response = $this->get($this->baseUrl().'/controlled-register?format=csv&from='.Carbon::today()->toDateString().'&to='.Carbon::today()->toDateString());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();
        $this->assertIsString($csv);
        $this->assertStringContainsString('occurred_at,direction,product_name', $csv);
        $this->assertStringContainsString('Morphine 10mg', $csv);
        $this->assertStringContainsString('ORD-2026-001', $csv);
        $this->assertStringContainsString('Sofiane Merbah', $csv);

        // Période sans mouvement → en-tête seul.
        $empty = $this->get($this->baseUrl().'/controlled-register?format=csv&from=2020-01-01&to=2020-01-31');
        $empty->assertStatus(200);
        $emptyCsv = (string) $empty->getContent();
        $this->assertStringNotContainsString('Morphine', $emptyCsv);
        $this->assertSame(1, substr_count(trim($emptyCsv), "\n") + 1);
    }
}
