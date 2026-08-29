<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC08 (#5884) — Isolation cross-tenant du journal comptable.
 *
 * Verrouille l'isolation portée par `BelongsToCompany` (scope global
 * fail-closed #3727) : un manager du tenant A ne voit que les écritures
 * comptables du tenant A — au niveau API et au niveau modèle.
 */
class AccountingTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = $this->tenant('tenant-a', 'a.test');
        $this->companyB = $this->tenant('tenant-b', 'b.test');
        $this->managerA = $this->manager($this->companyA, 'a.test');
    }

    private function tenant(string $slug, string $domain): Company
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$domain,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        return $company;
    }

    private function manager(Company $company, string $domain): Employee
    {
        $manager = new Employee([
            'email' => 'manager@'.$domain,
            'first_name' => 'Mgr',
            'last_name' => 'A',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
            'status' => 'active',
        ])->save();

        return $manager;
    }

    private function makeEntry(Company $company, float $amount): AccountingJournalEntry
    {
        // Contrainte `journal_debit_credit_exclusive` : une écriture porte le
        // débit OU le crédit, jamais les deux.
        return AccountingJournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_date' => '2026-06-15',
            'period' => '2026-06',
            'source_type' => 'manual',
            // Contrainte `journal_source_account_unique` (company_id, source_type,
            // source_id, account_code) : un source_id distinct par écriture.
            'source_id' => random_int(1000, 999999),
            'account_code' => '601000',
            'account_label' => 'Achats',
            'debit' => $amount,
            'credit' => 0, // colonne NOT NULL default 0 (migration 5234)
            'piece' => 'ECR-'.uniqid(),
            'description' => 'Écriture de test',
        ]);
    }

    public function test_manager_sees_only_own_tenants_journal_entries(): void
    {
        $this->makeEntry($this->companyA, 100);
        $this->makeEntry($this->companyA, 50);
        $this->makeEntry($this->companyB, 999);
        $this->makeEntry($this->companyB, 999);
        $this->makeEntry($this->companyB, 999);

        Sanctum::actingAs($this->managerA);

        $response = $this->getJson('/api/v1/accounting/journal?period=2026-06')
            ->assertOk();

        $this->assertCount(2, $response->json('entries'));

        foreach ($response->json('entries') as $entry) {
            $this->assertNotSame(999.0, (float) $entry['debit']);
        }
    }

    public function test_journal_scope_filters_other_tenants_entries_at_model_level(): void
    {
        $this->makeEntry($this->companyA, 100);
        $this->makeEntry($this->companyA, 50);
        $this->makeEntry($this->companyB, 999);

        app()->instance('current_company', $this->companyA);

        $visible = AccountingJournalEntry::query()
            ->where('period', '2026-06')
            ->get();

        $this->assertCount(2, $visible);
        foreach ($visible as $entry) {
            $this->assertSame($this->companyA->id, $entry->company_id);
        }
    }
}
