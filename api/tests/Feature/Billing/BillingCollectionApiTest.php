<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\BillingCollection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7863 (Encaissements) — encaissements enregistrés au local (espèces / TPE
 * au comptoir) : POST /billing/collections + listing GET paginé.
 *
 * Contrat verrouillé ici :
 *   1. le manager principal enregistre un encaissement (montant, devise,
 *      mode, note, date) et le retrouve dans le listing paginé ;
 *   2. validation : montant positif requis, devise 3 lettres, mode restreint ;
 *   3. RBAC : un manager non principal est refusé (403) ;
 *   4. isolation cross-tenant : le tenant B ne voit jamais les encaissements
 *      du tenant A.
 */
class BillingCollectionApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        return $company;
    }

    private function principal(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    public function test_principal_records_and_lists_local_collections(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->principal($company));

        $created = $this->postJson('/api/v1/billing/collections', [
            'amount' => 42.5,
            'currency' => 'eur',
            'method' => 'cash',
            'note' => 'Table 4 — règlement espèces',
            'collected_at' => '2026-09-20T12:30:00Z',
        ]);
        $created->assertCreated();
        $created->assertJsonPath('data.amount', 42.5);
        // Devise normalisée en majuscules.
        $created->assertJsonPath('data.currency', 'EUR');
        $created->assertJsonPath('data.method', 'cash');
        $created->assertJsonPath('data.note', 'Table 4 — règlement espèces');

        // Scoping : la ligne porte le company_id du tenant courant.
        $id = (int) $created->json('data.id');
        $row = DB::table('billing_collections')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame((string) $company->id, (string) $row->company_id);

        // Mode par défaut : cash ; collected_at par défaut : maintenant.
        $this->postJson('/api/v1/billing/collections', [
            'amount' => 10,
            'currency' => 'EUR',
        ])->assertCreated()->assertJsonPath('data.method', 'cash');

        $list = $this->getJson('/api/v1/billing/collections');
        $list->assertOk();
        $this->assertCount(2, $list->json('data.items'));
        $this->assertSame(2, $list->json('data.meta.total'));

        // Pagination : per_page borné et respecté.
        $paged = $this->getJson('/api/v1/billing/collections?per_page=1');
        $paged->assertOk();
        $this->assertCount(1, $paged->json('data.items'));
        $this->assertSame(2, $paged->json('data.meta.last_page'));
    }

    public function test_validation_rejects_bad_payloads(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->principal($company));

        // Montant manquant / négatif ou nul.
        $this->postJson('/api/v1/billing/collections', ['currency' => 'EUR'])
            ->assertUnprocessable()->assertJsonValidationErrors(['amount']);
        $this->postJson('/api/v1/billing/collections', ['amount' => 0, 'currency' => 'EUR'])
            ->assertUnprocessable()->assertJsonValidationErrors(['amount']);
        $this->postJson('/api/v1/billing/collections', ['amount' => -5, 'currency' => 'EUR'])
            ->assertUnprocessable()->assertJsonValidationErrors(['amount']);

        // Devise invalide.
        $this->postJson('/api/v1/billing/collections', ['amount' => 10, 'currency' => 'EURO'])
            ->assertUnprocessable()->assertJsonValidationErrors(['currency']);

        // Mode hors allowlist.
        $this->postJson('/api/v1/billing/collections', [
            'amount' => 10,
            'currency' => 'EUR',
            'method' => 'crypto',
        ])->assertUnprocessable()->assertJsonValidationErrors(['method']);
    }

    public function test_non_principal_manager_is_forbidden(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->principal($company, 'comptable'));

        $this->getJson('/api/v1/billing/collections')->assertForbidden();
        $this->postJson('/api/v1/billing/collections', [
            'amount' => 10,
            'currency' => 'EUR',
        ])->assertForbidden();
    }

    public function test_cross_tenant_isolation_on_listing(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->principal($companyA));
        $this->postJson('/api/v1/billing/collections', [
            'amount' => 99.99,
            'currency' => 'EUR',
        ])->assertCreated();

        $companyB = $this->company();
        Sanctum::actingAs($this->principal($companyB));

        $list = $this->getJson('/api/v1/billing/collections');
        $list->assertOk();
        $this->assertCount(0, $list->json('data.items'));

        // La donnée du tenant A existe bien, elle n'est simplement pas visible.
        $this->assertSame(1, BillingCollection::query()->withoutGlobalScopes()->count());
    }
}
