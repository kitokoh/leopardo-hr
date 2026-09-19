<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API catalogue d'actes & facturation des soins — HC-007 (#7791, BC-30).
 *
 * Couvre : 401, solution inactive 403 (fail-closed), RBAC billing strict
 * (réception, praticien et employé lambda 403 partout), total recalculé
 * serveur (Σ lignes − remise) avec prix FIGÉS depuis le catalogue (montants
 * client ignorés, changement de tarif sans effet sur la facture), émission
 * numérotée HINV-YYYY-NNNN par tenant, facture émise non modifiable (422,
 * annulation seulement), paiement partiel → partially_paid avec solde
 * exact, sur-paiement 422, couverture exacte → paid (terminal), stats CA
 * du mois + impayés, isolation cross-tenant (404, 422).
 */
class HealthInvoiceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $billingA;

    private Employee $principalA;

    private Employee $receptionA;

    private Employee $lambdaA;

    private HealthPatient $patientA;

    private HealthCareAct $consultationAct;

    private HealthCareAct $examAct;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager/invoices';
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
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['healthmanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $billingA */
        $billingA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);
        $this->billingA = $billingA;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'superviseur',
        ]);
        $this->receptionA = $receptionA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        $this->patientA = $this->makePatient($companyA->id, 'PAT-2026-0001', 'Amine', 'Kaci');
        $this->consultationAct = $this->makeAct($companyA->id, 'CONS-G', 'Consultation generale', 'consultation', '2500.00');
        $this->examAct = $this->makeAct($companyA->id, 'ECHO-ABD', 'Echographie abdominale', 'examination', '6000.00');
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl())->assertStatus(401);
        $this->getJson('/api/v1/health-manager/care-acts')->assertStatus(401);
        $this->getJson('/api/v1/health-manager/billing/stats')->assertStatus(401);
    }

    public function test_inactive_solution_gets_403_fail_closed(): void
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

        $this->getJson($this->baseUrl())
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson('/api/v1/health-manager/care-acts')->assertStatus(403);
        $this->getJson('/api/v1/health-manager/billing/stats')->assertStatus(403);
    }

    public function test_billing_rbac_is_strict(): void
    {
        // Employé lambda : 403 partout.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl())->assertStatus(403);
        $this->getJson('/api/v1/health-manager/care-acts')->assertStatus(403);
        $this->getJson('/api/v1/health-manager/billing/stats')->assertStatus(403);

        // La RÉCEPTION ne facture pas (health.billing + health.admin
        // seulement — critère HC-007).
        Sanctum::actingAs($this->receptionA);
        $this->getJson($this->baseUrl())->assertStatus(403);
        $this->postJson('/api/v1/health-manager/care-acts', [])->assertStatus(403);
        $this->getJson('/api/v1/health-manager/billing/stats')->assertStatus(403);

        // Le COMPTABLE (health.billing) gère le catalogue et les factures.
        Sanctum::actingAs($this->billingA);
        $this->getJson('/api/v1/health-manager/care-acts')->assertStatus(200);
        $this->getJson($this->baseUrl())->assertStatus(200);

        // La DIRECTION (health.admin) aussi.
        Sanctum::actingAs($this->principalA);
        $this->getJson('/api/v1/health-manager/care-acts')->assertStatus(200);
        $this->getJson('/api/v1/health-manager/billing/stats')->assertStatus(200);
    }

    public function test_totals_are_computed_server_side_with_frozen_prices(): void
    {
        Sanctum::actingAs($this->billingA);

        // Les montants envoyés par le client sont IGNORÉS (total recalculé
        // serveur) : 2 consultations + 1 échographie − 1000 de remise.
        $response = $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'discount' => 1000,
            'total' => '1.00',
            'subtotal' => '1.00',
            'items' => [
                ['care_act_id' => $this->consultationAct->id, 'quantity' => 2, 'unit_price' => '0.01'],
                ['care_act_id' => $this->examAct->id, 'quantity' => 1],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', '11000.00')
            ->assertJsonPath('data.discount', '1000.00')
            ->assertJsonPath('data.total', '10000.00')
            ->assertJsonPath('data.number', null);

        $invoiceId = (int) $response->json('data.id');

        // Prix FIGÉS : changer le tarif du catalogue ne modifie JAMAIS la
        // facture (critère HC-007).
        $this->putJson('/api/v1/health-manager/care-acts/'.$this->consultationAct->id, ['price' => 9999])
            ->assertStatus(200)
            ->assertJsonPath('data.price', '9999.00');

        $this->getJson($this->baseUrl().'/'.$invoiceId)
            ->assertStatus(200)
            ->assertJsonPath('data.total', '10000.00')
            ->assertJsonPath('data.items.0.unit_price', '2500.00');

        // Ligne persistée avec company_id posé côté serveur.
        $this->assertSame(
            $this->companyA->id,
            (string) DB::table('health_invoice_items')->where('invoice_id', $invoiceId)->value('company_id')
        );

        // Facture sans ligne → 422 (≥ 1 ligne obligatoire).
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'items' => [],
        ])->assertStatus(422);
    }

    public function test_issue_numbers_invoice_per_tenant_and_issued_is_not_editable(): void
    {
        Sanctum::actingAs($this->billingA);
        $year = now()->format('Y');

        $first = $this->createDraft();
        $second = $this->createDraft();

        $this->postJson($this->baseUrl().'/'.$first.'/issue')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.number', 'HINV-'.$year.'-0001');
        $this->postJson($this->baseUrl().'/'.$second.'/issue')
            ->assertStatus(200)
            ->assertJsonPath('data.number', 'HINV-'.$year.'-0002');

        // Ré-émettre → 422 (machine à états).
        $this->postJson($this->baseUrl().'/'.$first.'/issue')
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVALID_STATUS_TRANSITION');

        // Facture ÉMISE non modifiable → 422 (annulation seulement).
        $this->putJson($this->baseUrl().'/'.$first, ['discount' => 0])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_NOT_EDITABLE');

        // Annulation OK ; une facture annulée est TERMINALE.
        $this->postJson($this->baseUrl().'/'.$first.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
        $this->postJson($this->baseUrl().'/'.$first.'/issue')->assertStatus(422);
        $this->postJson($this->baseUrl().'/'.$first.'/payments', ['amount' => 100, 'method' => 'cash'])
            ->assertStatus(422);

        // La numérotation du tenant B est INDÉPENDANTE.
        /** @var Employee $billingB */
        $billingB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');
        $actB = $this->makeAct($this->companyB->id, 'CONS-G', 'Consultation', 'consultation', '300.00');

        Sanctum::actingAs($billingB);
        $invoiceB = (int) $this->postJson($this->baseUrl(), [
            'patient_id' => $patientB->id,
            'items' => [['care_act_id' => $actB->id, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');
        $this->postJson($this->baseUrl().'/'.$invoiceB.'/issue')
            ->assertStatus(200)
            ->assertJsonPath('data.number', 'HINV-'.$year.'-0001');
    }

    public function test_partial_payment_and_overpayment_rules(): void
    {
        Sanctum::actingAs($this->billingA);

        $invoiceId = $this->createDraft();

        // Un BROUILLON n'accepte pas de paiement → 422.
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 100, 'method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVALID_STATUS_TRANSITION');

        $this->postJson($this->baseUrl().'/'.$invoiceId.'/issue')->assertStatus(200);

        // Total 10000.00 — sur-paiement REFUSÉ (422, critère HC-007).
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 10000.01, 'method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_OVERPAYMENT');

        // Paiement PARTIEL → partially_paid, solde EXACT.
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 4000, 'method' => 'cash'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', '4000.00')
            ->assertJsonPath('data.balance', '6000.00');

        // Sur-paiement du SOLDE refusé aussi.
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 6000.01, 'method' => 'card'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVOICE_OVERPAYMENT');

        // Couverture EXACTE du solde → paid (terminal).
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 6000, 'method' => 'transfer'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance', '0.00');

        // Une facture PAYÉE n'accepte plus rien : ni paiement ni annulation.
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 1, 'method' => 'cash'])
            ->assertStatus(422);
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVALID_STATUS_TRANSITION');

        // Montant nul ou négatif → 422 (validation).
        $this->postJson($this->baseUrl().'/'.$invoiceId.'/payments', ['amount' => 0, 'method' => 'cash'])
            ->assertStatus(422);
    }

    public function test_draft_update_recomputes_totals(): void
    {
        Sanctum::actingAs($this->billingA);

        $invoiceId = $this->createDraft();

        // Remplacer les lignes + remise : totaux recalculés serveur.
        $this->putJson($this->baseUrl().'/'.$invoiceId, [
            'discount' => 500,
            'items' => [
                ['care_act_id' => $this->examAct->id, 'quantity' => 2],
            ],
        ])->assertStatus(200)
            ->assertJsonPath('data.subtotal', '12000.00')
            ->assertJsonPath('data.discount', '500.00')
            ->assertJsonPath('data.total', '11500.00');

        // Un acte INACTIF n'est plus facturable → 422.
        $this->putJson('/api/v1/health-manager/care-acts/'.$this->examAct->id, ['is_active' => false])
            ->assertStatus(200);
        $this->putJson($this->baseUrl().'/'.$invoiceId, [
            'items' => [['care_act_id' => $this->examAct->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_billing_stats_report_month_revenue_and_outstanding(): void
    {
        Sanctum::actingAs($this->billingA);

        // Facture 1 : émise et payée 4000 ce mois-ci (reste 6000 d'impayé).
        $paid = $this->createDraft();
        $this->postJson($this->baseUrl().'/'.$paid.'/issue')->assertStatus(200);
        $this->postJson($this->baseUrl().'/'.$paid.'/payments', ['amount' => 4000, 'method' => 'cash'])
            ->assertStatus(201);

        // Facture 2 : émise, entièrement impayée (10000).
        $unpaid = $this->createDraft();
        $this->postJson($this->baseUrl().'/'.$unpaid.'/issue')->assertStatus(200);

        // Facture 3 : brouillon — ne compte NI en CA NI en impayés.
        $this->createDraft();

        // Paiement d'un AUTRE tenant : jamais compté.
        /** @var Employee $billingB */
        $billingB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');
        $actB = $this->makeAct($this->companyB->id, 'CONS-G', 'Consultation', 'consultation', '300.00');
        Sanctum::actingAs($billingB);
        $invoiceB = (int) $this->postJson($this->baseUrl(), [
            'patient_id' => $patientB->id,
            'items' => [['care_act_id' => $actB->id, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id');
        $this->postJson($this->baseUrl().'/'.$invoiceB.'/issue')->assertStatus(200);
        $this->postJson($this->baseUrl().'/'.$invoiceB.'/payments', ['amount' => 300, 'method' => 'cash'])
            ->assertStatus(201);

        Sanctum::actingAs($this->billingA);
        $this->getJson('/api/v1/health-manager/billing/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.month_revenue', '4000.00')
            ->assertJsonPath('data.outstanding_total', '16000.00')
            ->assertJsonPath('data.outstanding_count', 2);
    }

    public function test_cross_tenant_is_isolated(): void
    {
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');
        $actB = $this->makeAct($this->companyB->id, 'CONS-B', 'Consultation B', 'consultation', '300.00');

        Sanctum::actingAs($this->billingA);

        // Références du tenant B → 422 (Rule::exists scopées).
        $this->postJson($this->baseUrl(), [
            'patient_id' => $patientB->id,
            'items' => [['care_act_id' => $this->consultationAct->id, 'quantity' => 1]],
        ])->assertStatus(422);
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'items' => [['care_act_id' => $actB->id, 'quantity' => 1]],
        ])->assertStatus(422);

        // Facture du tenant B → 404 depuis A.
        /** @var HealthInvoice $invoiceB */
        $invoiceB = HealthInvoice::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'patient_id' => $patientB->id,
            'subtotal' => '300.00',
            'discount' => '0.00',
            'total' => '300.00',
            'status' => HealthInvoice::STATUS_DRAFT,
        ]);

        $this->getJson($this->baseUrl().'/'.$invoiceB->id)->assertStatus(404);
        $this->putJson($this->baseUrl().'/'.$invoiceB->id, ['discount' => 0])->assertStatus(404);
        $this->postJson($this->baseUrl().'/'.$invoiceB->id.'/issue')->assertStatus(404);
        $this->postJson($this->baseUrl().'/'.$invoiceB->id.'/payments', ['amount' => 1, 'method' => 'cash'])
            ->assertStatus(404);

        // Acte du tenant B → 404 depuis A.
        $this->getJson('/api/v1/health-manager/care-acts/'.$actB->id)->assertStatus(404);
    }

    /**
     * Brouillon type : 2 consultations (2500) + 1 échographie (6000) −
     * 1000 de remise = total 10000.00.
     */
    private function createDraft(): int
    {
        return (int) $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'discount' => 1000,
            'items' => [
                ['care_act_id' => $this->consultationAct->id, 'quantity' => 2],
                ['care_act_id' => $this->examAct->id, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data.id');
    }

    private function makePatient(string $companyId, string $mrn, string $firstName, string $lastName): HealthPatient
    {
        /** @var HealthPatient $patient */
        $patient = HealthPatient::query()->forceCreate([
            'company_id' => $companyId,
            'mrn' => $mrn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        return $patient;
    }

    private function makeAct(string $companyId, string $code, string $name, string $category, string $price): HealthCareAct
    {
        /** @var HealthCareAct $act */
        $act = HealthCareAct::query()->forceCreate([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'is_active' => true,
        ]);

        return $act;
    }
}
