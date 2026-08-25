<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5230 — tableaux de bord comptables (factures émises, encaissements,
 * impayés aging, dépenses fournisseurs + export CSV).
 *
 * Les fixtures utilisent des dates RELATIVES à aujourd'hui : les assertions
 * (totaux, buckets d'aging, retard en jours) restent stables quel que soit le
 * jour d'exécution.
 */
class AccountingDashboardTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        $this->seedFixtures($companyA);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function contact(Company $company, string $type, string $name): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => $type,
            'name' => $name,
            'email' => strtolower($name).'@example.com',
            'source' => 'manual',
        ]);

        return $contact;
    }

    /**
     * Documents/paiements de démonstration (dates relatives à aujourd'hui).
     */
    private function seedFixtures(Company $company): void
    {
        $customer = $this->contact($company, ContactType::Customer->value, 'Client Alpha');
        $supplier = $this->contact($company, ContactType::Supplier->value, 'Fournisseur Beta');

        // En retard de 5 j → bucket 0_30, dû 1000.
        $overdue30 = $this->document($company, $customer, 'FAC-A-0001', 'sent', 1000.0, 0.0, now()->subDays(10), now()->subDays(5));
        // En retard de 40 j → bucket 31_60, dû 1500 (partiel 500).
        $this->document($company, $customer, 'FAC-A-0002', 'partially_paid', 2000.0, 500.0, now()->subDays(45), now()->subDays(40));
        // Payée → émise mais pas impayée.
        $paidDoc = $this->document($company, $customer, 'FAC-A-0003', 'paid', 3000.0, 3000.0, now()->subDays(20), now()->subDays(15));
        // Brouillon → exclue partout.
        $this->document($company, $customer, 'FAC-A-0004', 'draft', 4000.0, 0.0, now()->subDays(8), now()->subDays(1));
        // En retard de 115 j → bucket 90_plus, dû 800.
        $this->document($company, $customer, 'FAC-A-0005', 'sent', 800.0, 0.0, now()->subDays(120), now()->subDays(115));
        // Achat fournisseur (dépense) — pas encore échue.
        $this->document($company, $supplier, 'FAC-A-0006', 'sent', 1500.0, 0.0, now()->subDays(6), now()->addDays(10));

        // Encaissements dans la période large [début de mois courant - 6 mois, aujourd'hui].
        AccountingPayment::query()->create([
            'company_id' => $company->id,
            'document_id' => $overdue30->id,
            'amount' => 500.0,
            'method' => 'cash',
            'received_at' => now()->subDays(39)->toDateString(),
            'status' => 'recorded',
        ]);
        AccountingPayment::query()->create([
            'company_id' => $company->id,
            'document_id' => $paidDoc->id,
            'amount' => 3000.0,
            'method' => 'bank_transfer',
            'received_at' => now()->subDays(10)->toDateString(),
            'status' => 'matched',
        ]);
    }

    private function document(
        Company $company,
        AccountingContact $contact,
        string $number,
        string $status,
        float $totalTtc,
        float $paidAmount,
        Carbon $issueDate,
        Carbon $dueDate,
    ): AccountingDocument {
        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => $number,
            'status' => $status,
            'contact_id' => $contact->id,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'currency' => 'DZD',
            'subtotal_ht' => $totalTtc,
            'tax_amount' => 0.0,
            'total_ttc' => $totalTtc,
            'tva_rate' => 0.0,
            'paid_amount' => $paidAmount,
        ]);

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function period(): array
    {
        return [
            'from' => now()->subMonths(6)->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];
    }

    public function test_dashboard_aggregations_are_correct(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/dashboard?'.http_build_query($this->period()));

        $response->assertStatus(200);

        // Factures émises : 4 (hors draft), total 1000+2000+3000+800 = 6800.
        $response->assertJsonPath('data.invoices.count', 4);
        $response->assertJsonPath('data.invoices.total_ttc', 6800);

        // Encaissements : 2 paiements, total 500+3000 = 3500.
        $response->assertJsonPath('data.collections.count', 2);
        $response->assertJsonPath('data.collections.total', 3500);

        // Dépenses fournisseurs : 1 document, 1500.
        $response->assertJsonPath('data.expenses.count', 1);
        $response->assertJsonPath('data.expenses.total_ttc', 1500);

        // Impayés : 4 documents (1000 + 1500 + 800 + 1500 non échu).
        $response->assertJsonPath('data.outstanding.count', 4);
        $response->assertJsonPath('data.outstanding.total_due', 4800);
    }

    public function test_outstanding_aging_buckets_are_correct(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/dashboard?'.http_build_query($this->period()));

        $response->assertStatus(200);

        $aging = $response->json('data.outstanding.aging');
        $this->assertIsArray($aging);

        $byBucket = [];

        foreach ($aging as $row) {
            $bucket = $row['bucket'] ?? '';
            $byBucket[(string) $bucket] = $row;
        }

        // Retard 5 j → 0_30 ; 40 j → 31_60 ; 115 j → 90_plus.
        $this->assertSame(1, $byBucket['0_30']['count']);
        $this->assertSame(1000, $byBucket['0_30']['total_due']);
        $this->assertSame(1, $byBucket['31_60']['count']);
        $this->assertSame(1500, $byBucket['31_60']['total_due']);
        $this->assertSame(0, $byBucket['61_90']['count']);
        $this->assertSame(1, $byBucket['90_plus']['count']);
        $this->assertSame(800, $byBucket['90_plus']['total_due']);

        // La liste contient 4 impayés, dont la facture non échue (due future).
        $list = $response->json('data.outstanding.list');
        $this->assertCount(4, $list);
        $this->assertContains('FAC-A-0001', array_column($list, 'number'));
        $this->assertContains('FAC-A-0006', array_column($list, 'number'));
    }

    public function test_csv_export_contains_outstanding_lines(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->get('/api/v1/accounting/dashboard/export?'.http_build_query($this->period()));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="accounting-dashboard-outstanding-'.$this->period()['from'].'_'.$this->period()['to'].'.csv"');

        $csv = (string) $response->getContent();

        $this->assertStringContainsString('number,contact,issue_date,due_date,days_late,total_ttc,paid_amount,due_amount,status', $csv);
        $this->assertStringContainsString('FAC-A-0001', $csv);
        $this->assertStringContainsString('FAC-A-0005', $csv);
        $this->assertStringContainsString('Client Alpha', $csv);
    }

    public function test_dashboard_rejects_invalid_period(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/dashboard?from=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_rbac_forbids_employee_and_marketing(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));
        $this->getJson('/api/v1/accounting/dashboard')->assertStatus(403);
        $this->get('/api/v1/accounting/dashboard/export')->assertStatus(403);

        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));
        $this->getJson('/api/v1/accounting/dashboard')->assertStatus(403);
        $this->get('/api/v1/accounting/dashboard/export')->assertStatus(403);
    }

    public function test_principal_can_access_dashboard(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $this->getJson('/api/v1/accounting/dashboard?'.http_build_query($this->period()))
            ->assertStatus(200)
            ->assertJsonPath('data.invoices.count', 4);
    }

    public function test_tenant_isolation(): void
    {
        // L'entreprise B n'a AUCUNE donnée : le dashboard est vide et ne fuit
        // rien de l'entreprise A.
        Sanctum::actingAs($this->manager($this->companyB));

        $response = $this->getJson('/api/v1/accounting/dashboard?'.http_build_query($this->period()));

        $response->assertStatus(200);
        $response->assertJsonPath('data.invoices.count', 0);
        $response->assertJsonPath('data.collections.count', 0);
        $response->assertJsonPath('data.expenses.count', 0);
        $response->assertJsonPath('data.outstanding.count', 0);
        $response->assertJsonPath('data.outstanding.total_due', 0);
        $this->assertSame([], $response->json('data.outstanding.list'));
        $this->assertSame(
            [
                ['bucket' => '0_30', 'count' => 0, 'total_due' => 0],
                ['bucket' => '31_60', 'count' => 0, 'total_due' => 0],
                ['bucket' => '61_90', 'count' => 0, 'total_due' => 0],
                ['bucket' => '90_plus', 'count' => 0, 'total_due' => 0],
            ],
            $response->json('data.outstanding.aging'),
        );

        // L'export CSV de B ne contient aucun numéro de facture de A.
        $csv = (string) $this->get('/api/v1/accounting/dashboard/export')->getContent();
        $this->assertStringNotContainsString('FAC-A-', $csv);
    }
}
