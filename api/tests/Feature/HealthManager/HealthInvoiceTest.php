<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API actes de soins & facturation — HC-007 (#7791, BC-30).
 *
 * Couvre : matrice 401 / 403 flag inactif / 403 lambda ; CRUD catalogue
 * (rôle billing via health_staff_roles) + suppression refusée si référencé
 * (422 HEALTH_RESOURCE_IN_USE) ; facture brouillon (prix FIGÉS, totaux
 * serveur, numérotation HINV-YYYY-NNNN séquentielle) ; émission ; paiements
 * (partiel → partially_paid, solde exact → paid, sur-paiement 422
 * HEALTH_OVERPAYMENT) ; annulation (payée 422, brouillon OK) ; immutabilité
 * structurelle (pas de route PUT) ; réception lecture seule ; isolation
 * cross-tenant 404.
 */
class HealthInvoiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $billingA;

    private Employee $receptionA;

    private Employee $lambdaA;

    private HealthPatient $patientA;

    private HealthCareAct $careActA;

    private HealthInvoice $invoiceB;

    private HealthCareAct $careActB;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['healthmanager' => true],
        ]);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['healthmanager' => true],
        ]);

        /** @var Employee $adminA */
        $adminA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        /** @var Employee $billingA */
        $billingA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->billingA = $billingA;

        HealthStaffRole::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $billingA->id,
            'role' => HealthStaffRole::ROLE_BILLING,
        ]);

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->receptionA = $receptionA;

        HealthStaffRole::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $receptionA->id,
            'role' => HealthStaffRole::ROLE_RECEPTION,
        ]);

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var HealthPatient $patientA */
        $patientA = HealthPatient::query()->create([
            'company_id' => $companyA->id,
            'mrn' => 'PAT-2026-0001',
            'full_name' => 'Amine Kaci',
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);
        $this->patientA = $patientA;

        /** @var HealthCareAct $careActA */
        $careActA = HealthCareAct::query()->create([
            'company_id' => $companyA->id,
            'code' => 'CONS-GEN',
            'label' => 'Consultation générale',
            'category' => HealthCareAct::CATEGORY_CONSULTATION,
            'price' => '100.00',
            'currency' => 'DZD',
            'active' => true,
        ]);
        $this->careActA = $careActA;

        // Fixtures du tenant B (créées AVANT toute requête HTTP : hors
        // contexte tenant, company_id explicite conservé).
        /** @var HealthPatient $patientB */
        $patientB = HealthPatient::query()->create([
            'company_id' => $companyB->id,
            'mrn' => 'PAT-2026-0001',
            'full_name' => 'Rania Alaoui',
            'sex' => HealthPatient::SEX_FEMALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        /** @var HealthCareAct $careActB */
        $careActB = HealthCareAct::query()->create([
            'company_id' => $companyB->id,
            'code' => 'CONS-GEN',
            'label' => 'Consultation générale',
            'category' => HealthCareAct::CATEGORY_CONSULTATION,
            'price' => '200.00',
            'currency' => 'MAD',
            'active' => true,
        ]);
        $this->careActB = $careActB;

        /** @var HealthInvoice $invoiceB */
        $invoiceB = HealthInvoice::query()->create([
            'company_id' => $companyB->id,
            'number' => 'HINV-2026-9999',
            'patient_id' => (int) $patientB->getAttribute('id'),
            'status' => HealthInvoice::STATUS_DRAFT,
            'currency' => 'MAD',
            'subtotal' => '200.00',
            'discount' => '0.00',
            'total' => '200.00',
            'amount_paid' => '0.00',
        ]);
        $this->invoiceB = $invoiceB;
    }

    // ── Matrice d'accès ─────────────────────────────────────────────────

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/care-acts')->assertStatus(401);
        $this->getJson($this->baseUrl().'/invoices')->assertStatus(401);
        $this->postJson($this->baseUrl().'/invoices', [])->assertStatus(401);
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

        $this->getJson($this->baseUrl().'/care-acts')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/invoices')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/care-acts')->assertStatus(403);
        $this->postJson($this->baseUrl().'/care-acts', [
            'code' => 'X-01',
            'label' => 'Acte',
            'category' => 'other',
            'price' => 10,
        ])->assertStatus(403);
        $this->getJson($this->baseUrl().'/invoices')->assertStatus(403);
        $this->getJson($this->baseUrl().'/invoices/stats')->assertStatus(403);
        $this->postJson($this->baseUrl().'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte libre', 'unit_price' => 10, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    // ── Catalogue d'actes ───────────────────────────────────────────────

    public function test_billing_role_manages_care_acts(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        // Création
        $actId = $this->postJson($url.'/care-acts', [
            'code' => 'ECHO-ABD',
            'label' => 'Échographie abdominale',
            'category' => 'exam',
            'price' => 250.5,
        ])->assertStatus(201)
            ->assertJsonPath('data.currency', 'DZD')
            ->assertJsonPath('data.active', true)
            ->json('data.id');

        // Filtres index (category + active)
        $this->getJson($url.'/care-acts?category=exam')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ECHO-ABD');
        $this->getJson($url.'/care-acts?active=1')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Mise à jour
        $this->putJson($url.'/care-acts/'.$actId, ['price' => 300, 'active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.price', '300.00')
            ->assertJsonPath('data.active', false);

        $this->getJson($url.'/care-acts?active=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Suppression d'un acte non référencé
        $this->deleteJson($url.'/care-acts/'.$actId)->assertStatus(204);
        $this->assertDatabaseMissing('health_care_acts', ['id' => $actId]);
    }

    public function test_care_act_referenced_by_invoice_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [
                ['care_act_id' => $this->careActA->getAttribute('id'), 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $this->deleteJson($url.'/care-acts/'.$this->careActA->getAttribute('id'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_RESOURCE_IN_USE');

        $this->assertDatabaseHas('health_care_acts', ['id' => $this->careActA->getAttribute('id')]);
    }

    // ── Factures : brouillon, prix figés, numérotation ──────────────────

    public function test_draft_invoice_freezes_prices_and_recomputes_totals_server_side(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();
        $year = now()->format('Y');

        $response = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'discount' => 10,
            // Totaux client → IGNORÉS (recalcul serveur).
            'subtotal' => '1.00',
            'total' => '1.00',
            'items' => [
                // Ligne catalogue : prix client 999 IGNORÉ, prix figé 100.00.
                ['care_act_id' => $this->careActA->getAttribute('id'), 'quantity' => 2, 'unit_price' => 999],
                // Ligne libre.
                ['label' => 'Chambre individuelle', 'unit_price' => 50.5, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $invoiceId = $response->json('data.id');
        $response->assertJsonPath('data.number', sprintf('HINV-%s-0001', $year))
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', '351.50')
            ->assertJsonPath('data.discount', '10.00')
            ->assertJsonPath('data.total', '341.50')
            ->assertJsonPath('data.amount_paid', '0.00')
            ->assertJsonPath('data.items.0.unit_price', '100.00')
            ->assertJsonPath('data.items.0.label', 'Consultation générale')
            ->assertJsonPath('data.items.0.line_total', '200.00')
            ->assertJsonPath('data.items.1.label', 'Chambre individuelle')
            ->assertJsonPath('data.items.1.line_total', '151.50');

        // Changer le prix du catalogue ne réécrit PAS la ligne (prix figé).
        $this->putJson($url.'/care-acts/'.$this->careActA->getAttribute('id'), ['price' => 999.99])
            ->assertStatus(200);

        $this->getJson($url.'/invoices/'.$invoiceId)
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.unit_price', '100.00')
            ->assertJsonPath('data.items.0.line_total', '200.00')
            ->assertJsonPath('data.total', '341.50');

        // Numérotation séquentielle par tenant/année.
        $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte libre', 'unit_price' => 20, 'quantity' => 1]],
        ])->assertStatus(201)
            ->assertJsonPath('data.number', sprintf('HINV-%s-0002', $year));
    }

    // ── Cycle de vie : émission, paiements, annulation ──────────────────

    public function test_issue_then_partial_and_full_payments(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        $invoiceId = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['care_act_id' => $this->careActA->getAttribute('id'), 'quantity' => 2]],
        ])->assertStatus(201)->json('data.id');

        // Paiement sur un brouillon → refusé.
        $this->postJson($url.'/invoices/'.$invoiceId.'/payments', ['amount' => 10, 'method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_STATUS');

        // Émission : draft → issued + issued_at.
        $issued = $this->postJson($url.'/invoices/'.$invoiceId.'/issue')->assertStatus(200);
        $issued->assertJsonPath('data.status', 'issued');
        $this->assertNotNull($issued->json('data.issued_at'));

        // Ré-émission → refusée.
        $this->postJson($url.'/invoices/'.$invoiceId.'/issue')
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_STATUS');

        // Paiement partiel : 80 / 200 → partially_paid, solde exact.
        $this->postJson($url.'/invoices/'.$invoiceId.'/payments', [
            'amount' => 80,
            'method' => 'cash',
            'reference' => 'REC-001',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', '80.00')
            ->assertJsonPath('data.payments.0.amount', '80.00')
            ->assertJsonPath('data.payments.0.reference', 'REC-001');

        // Sur-paiement du solde (121 > 120 restant) → 422, rien n'est écrit.
        $this->postJson($url.'/invoices/'.$invoiceId.'/payments', ['amount' => 121, 'method' => 'card'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_OVERPAYMENT');
        $this->assertDatabaseCount('health_invoice_payments', 1);

        // Solde exact : 120 → paid.
        $this->postJson($url.'/invoices/'.$invoiceId.'/payments', ['amount' => 120, 'method' => 'transfer'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount_paid', '200.00');

        // Facture soldée : plus aucun encaissement.
        $this->postJson($url.'/invoices/'.$invoiceId.'/payments', ['amount' => 1, 'method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_STATUS');
    }

    public function test_cancel_rules(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        // Annulation d'un brouillon → OK.
        $draftId = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte libre', 'unit_price' => 30, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url.'/invoices/'.$draftId.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Annulation d'une facture PAYÉE → 422.
        $paidId = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte libre', 'unit_price' => 50, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url.'/invoices/'.$paidId.'/issue')->assertStatus(200);
        $this->postJson($url.'/invoices/'.$paidId.'/payments', ['amount' => 50, 'method' => 'cash'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        $this->postJson($url.'/invoices/'.$paidId.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_STATUS');
    }

    public function test_issued_invoice_has_no_modification_route(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        $invoiceId = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte libre', 'unit_price' => 10, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url.'/invoices/'.$invoiceId.'/issue')->assertStatus(200);

        // Contrat structurel : AUCUNE route de modification (PUT/PATCH → 405).
        $this->putJson($url.'/invoices/'.$invoiceId, ['discount' => 5])->assertStatus(405);
        $this->patchJson($url.'/invoices/'.$invoiceId, ['discount' => 5])->assertStatus(405);
    }

    // ── Filtres, stats, réception, cross-tenant ─────────────────────────

    public function test_index_filters_and_stats(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        $draftId = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte A', 'unit_price' => 100, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');

        $issuedId = $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte B', 'unit_price' => 200, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url.'/invoices/'.$issuedId.'/issue')->assertStatus(200);
        $this->postJson($url.'/invoices/'.$issuedId.'/payments', ['amount' => 50, 'method' => 'cash'])
            ->assertStatus(200);

        // Filtres (l'isolation tenant exclut la facture du tenant B).
        $this->getJson($url.'/invoices')->assertStatus(200)->assertJsonCount(2, 'data');
        $this->getJson($url.'/invoices?status=draft')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draftId);
        $this->getJson($url.'/invoices?patient_id='.$this->patientA->getAttribute('id'))
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
        $this->getJson($url.'/invoices?patient_id=999999')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Stats : compteurs, chiffre du mois (Σ encaissements), encours.
        $stats = $this->getJson($url.'/invoices/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.draft_count', 1)
            ->assertJsonPath('data.issued_count', 0)
            ->assertJsonPath('data.partially_paid_count', 1)
            ->assertJsonPath('data.paid_count', 0)
            ->assertJsonPath('data.currency', 'DZD');
        $this->assertEquals(50, $stats->json('data.month_revenue'));
        $this->assertEquals(150, $stats->json('data.outstanding'));
    }

    public function test_reception_can_view_invoices_but_cannot_create(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $this->getJson($url.'/invoices')->assertStatus(200);
        $this->getJson($url.'/invoices/stats')->assertStatus(200);

        $this->postJson($url.'/invoices', [
            'patient_id' => $this->patientA->getAttribute('id'),
            'items' => [['label' => 'Acte libre', 'unit_price' => 10, 'quantity' => 1]],
        ])->assertStatus(403);

        // Catalogue : direction + facturation UNIQUEMENT.
        $this->getJson($url.'/care-acts')->assertStatus(403);
    }

    public function test_cross_tenant_is_404(): void
    {
        Sanctum::actingAs($this->billingA);
        $url = $this->baseUrl();

        $this->getJson($url.'/invoices/'.$this->invoiceB->getAttribute('id'))->assertStatus(404);
        $this->postJson($url.'/invoices/'.$this->invoiceB->getAttribute('id').'/issue')->assertStatus(404);
        $this->putJson($url.'/care-acts/'.$this->careActB->getAttribute('id'), ['price' => 1])->assertStatus(404);
        $this->deleteJson($url.'/care-acts/'.$this->careActB->getAttribute('id'))->assertStatus(404);

        // Un patient d'un autre tenant n'est pas facturable (validation exists tenant-scopée).
        $this->postJson($url.'/invoices', [
            'patient_id' => $this->invoiceB->patient_id,
            'items' => [['label' => 'Acte libre', 'unit_price' => 10, 'quantity' => 1]],
        ])->assertStatus(422);
    }
}
