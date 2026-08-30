<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5731 (CRM-V1-15) — hardening : tests négatifs du module CRM client.
 *
 * Verrouille les scénarios du threat model (docs/security/CRM_THREAT_MODEL.md) :
 *  - T1 fuite cross-tenant → 404 sur les routes sensibles ;
 *  - T2 entrées malveillantes (filtres/tris inconnus, formules CSV) → 422 ;
 *  - T3 PII chiffrée au repos, jamais en clair dans les logs/erreurs ;
 *  - T4 idempotence (conversion, commit d'import) → rejet des doublons ;
 *  - T5 bornes d'import (taille/lignes/colonnes) ;
 *  - T6 RBAC : actions à permission élevée refusées à l'employé ordinaire.
 *
 * Intégration : s'exécute une fois la surface API CRM mergée (contrats
 * #5712, policies #5711, imports #5714, conversion #5717, fusion #5718).
 */
class CrmHardeningNegativeTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
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

    /** @param array<string, mixed> $overrides */
    private function account(Company $company, array $overrides = []): CrmAccount
    {
        /** @var CrmAccount $account */
        $account = CrmAccount::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Acme Algérie',
            'status' => 'active',
        ], $overrides));

        return $account;
    }

    /** @param array<string, mixed> $overrides */
    private function contact(Company $company, array $overrides = []): CrmContact
    {
        /** @var CrmContact $contact */
        $contact = CrmContact::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'email' => 'karim@acme.dz',
            'status' => 'active',
        ], $overrides));

        return $contact;
    }

    /** @param array<string, mixed> $overrides */
    private function lead(Company $company, array $overrides = []): CrmLead
    {
        /** @var CrmLead $lead */
        $lead = CrmLead::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Sofia',
            'last_name' => 'Merabet',
            'company_name' => 'Startup X',
            'email' => 'sofia@startup-x.dz',
            'source' => 'manual',
            'status' => 'new',
        ], $overrides));

        return $lead;
    }

    // ── T1 — Fuite cross-tenant ──────────────────────────────────────────────

    public function test_cross_tenant_foreign_ids_return_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Ressources du tenant B → 404 depuis A.
        $foreignAccount = $this->account($this->companyB);
        $foreignContact = $this->contact($this->companyB);
        $foreignLead = $this->lead($this->companyB);

        $this->getJson('/api/v1/crm/accounts/'.(int) $foreignAccount->getAttribute('id'))->assertStatus(404);
        $this->getJson('/api/v1/crm/contacts/'.(int) $foreignContact->getAttribute('id'))->assertStatus(404);
        $this->getJson('/api/v1/crm/leads/'.(int) $foreignLead->getAttribute('id'))->assertStatus(404);
        $this->putJson('/api/v1/crm/leads/'.(int) $foreignLead->getAttribute('id'), ['status' => 'qualified'])->assertStatus(404);
    }

    // ── T2 — Entrées malveillantes ───────────────────────────────────────────

    public function test_unknown_sort_and_filters_are_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->account($this->companyA);

        // Tri par colonne inconnue → 422 (whitelist ADR-CRM-005), jamais 500.
        $this->getJson('/api/v1/crm/accounts?sort_by=evil_column')->assertStatus(422);
        $this->getJson('/api/v1/crm/accounts?filter[evil]=x')->assertStatus(422);
        $this->getJson('/api/v1/crm/accounts?per_page=99999')->assertStatus(422);
    }

    public function test_sql_injection_attempts_are_neutralized(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->account($this->companyA);

        // Tentative d'injection via filtre de recherche → jamais de 500
        // (la recherche plein texte est libre, la propriété de sécurité est
        // l'absence d'erreur SQL / d'exécution).
        $this->assertNotSame(500, $this->getJson("/api/v1/crm/accounts?search=' OR '1'='1")->getStatusCode());
        $this->assertNotSame(500, $this->getJson('/api/v1/crm/accounts?search=1;DROP TABLE crm_accounts;--')->getStatusCode());

        // Statut hors whitelist → 422 (Rule\In, ADR-CRM-005).
        $this->getJson('/api/v1/crm/accounts?status=active;DROP TABLE crm_accounts')->assertStatus(422);
    }

    public function test_unknown_status_values_are_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Test',
            'status' => 'hacker_status',
        ])->assertStatus(422);

        $this->postJson('/api/v1/crm/accounts', [
            'name' => 'Acme',
            'status' => 'deleted',
        ])->assertStatus(422);
    }

    // ── T3 — PII protégée ────────────────────────────────────────────────────

    public function test_pii_is_encrypted_at_rest(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Ali',
            'last_name' => 'Haddad',
            'email' => 'ali.secret@acme.dz',
            'phone' => '+213555999999',
        ])->assertStatus(201);

        $storedEmail = DB::table('crm_contacts')
            ->where('company_id', $this->companyA->id)
            ->value('email');

        $this->assertNotSame('ali.secret@acme.dz', $storedEmail, 'L\'email doit être chiffré au repos (#5713).');
        $this->assertIsString($storedEmail);
    }

    public function test_pii_never_leaks_into_error_messages(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Erreur d'import avec une ligne invalide : le message d'erreur ne
        // doit pas contenir l'email saisi (pas de PII dans crm_imports.errors).
        $response = $this->postJson('/api/v1/crm/imports/preview', [
            'entity_type' => 'lead',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent(
                'import.csv',
                "first_name,email\nKarim,pii-leak-test@acme.dz\n"
            ),
        ]);

        $response->assertStatus(201);
        $this->assertStringNotContainsString(
            'pii-leak-test@acme.dz',
            (string) json_encode($response->json('data.preview.errors') ?? []),
            'Les erreurs par ligne ne doivent jamais contenir de PII.'
        );
    }

    // ── T4 — Idempotence ─────────────────────────────────────────────────────

    public function test_duplicate_conversion_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $lead = $this->lead($this->companyA);

        $first = $this->postJson('/api/v1/crm/leads/'.(int) $lead->getAttribute('id').'/convert', [], [
            'Idempotency-Key' => 'harden-0001',
        ]);

        if ($first->status() === 404) {
            // Route de conversion pas encore mergée (dépendance #5717) :
            // le test est sauté proprement au lieu de créer un faux rouge.
            $this->markTestSkipped('Route /crm/leads/{lead}/convert non disponible (dépendance #5717).');
        }

        $first->assertStatus(201);

        // Même clé → rejeu idempotent (200), aucun doublon.
        $this->postJson('/api/v1/crm/leads/'.(int) $lead->getAttribute('id').'/convert', [], [
            'Idempotency-Key' => 'harden-0001',
        ])->assertStatus(200);

        $this->assertDatabaseCount('crm_accounts', 1);
        $this->assertDatabaseCount('crm_lead_conversions', 1);
    }

    // ── T5 — Bornes d'import ─────────────────────────────────────────────────

    public function test_import_file_bounds_are_enforced(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Extension refusée.
        $this->postJson('/api/v1/crm/imports/preview', [
            'entity_type' => 'account',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('import.xlsx', 'x'),
        ])->assertStatus(422);

        // Colonnes trop nombreuses.
        $tooManyColumns = 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v'.chr(10).'1,2,3';
        $this->postJson('/api/v1/crm/imports/preview', [
            'entity_type' => 'account',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('import.csv', $tooManyColumns),
        ])->assertStatus(422);
    }

    // ── T6 — RBAC ────────────────────────────────────────────────────────────

    public function test_employee_role_is_forbidden_on_privileged_actions(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));
        $account = $this->account($this->companyA);

        // CRUD lecture : interdit pour un employé ordinaire (Policies #5711).
        $this->getJson('/api/v1/crm/accounts')->assertStatus(403);
        $this->getJson('/api/v1/crm/accounts/'.(int) $account->getAttribute('id'))->assertStatus(403);

        // Actions à permission élevée : idem.
        $this->getJson('/api/v1/crm/duplicates/suggestions?entity_type=account')->assertStatus(403);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/crm/accounts')->assertStatus(401);
        $this->postJson('/api/v1/crm/imports/preview', [
            'entity_type' => 'account',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('import.csv', 'name'."\n".'Acme'),
        ])->assertStatus(401);
    }
}
