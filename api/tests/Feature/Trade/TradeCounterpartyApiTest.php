<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contreparties du vendeur B2B — #9779.
 *
 * ## Le defaut que ce test protege
 *
 * `trade_counterparties` avait table, modele et factory depuis #8478, mais
 * AUCUNE route. Et `POST /trade/rfqs/{rfq}/send` exige `counterparty_ids`
 * (`min:1`) : sans contrepartie creable, la diffusion d'une demande de prix
 * etait impossible pour un tenant reel. Ce test verifie que le chemin existe
 * ET qu'il ne peut pas etre detourne.
 *
 * ## Deux invariants de plus
 *
 * - `verification_status` n'est PAS auto-declarable : `verified` doit etre
 *   refuse a la creation. Une contrepartie s'auto-verifiee ferait passer une
 *   declaration pour un controle qui n'a pas eu lieu.
 * - Une contrepartie d'une autre entreprise est INTROUVABLE (404), jamais 403.
 */
class TradeCounterpartyApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_a_counterparty_with_a_role_can_be_created(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Sahel Trading SARL',
            'country' => 'ML',
            'city' => 'Bamako',
            'email' => 'contact@sahel.example',
            'roles' => ['supplier', 'wholesaler'],
        ])->assertStatus(201);

        $response->assertJsonPath('data.name', 'Sahel Trading SARL')
            ->assertJsonPath('data.verification_status', 'none')
            ->assertJsonPath('data.roles', ['supplier', 'wholesaler']);

        // `company_id` n'est jamais expose dans la charge utile.
        self::assertArrayNotHasKey('company_id', $response->json('data'));
    }

    public function test_a_counterparty_without_a_role_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Sans role, on ne sait pas dans quel sens on traite avec ce tiers :
        // la contrepartie ne peut servir a aucune operation.
        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Sans role',
            'roles' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Role invente',
            'roles' => ['god_mode'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_a_client_cannot_self_assert_verification(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // « verified » signifie « verifie par un membre de l'equipe ». Le
        // laisser passer a la creation reviendrait a laisser un tiers se
        // declerer verifie — exactement ce qu'un annuaire ne doit pas tolerer.
        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Auto-verifie',
            'roles' => ['buyer'],
            'verification_status' => 'verified',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verification_status']);

        // `pending` est en revanche une demande legitime.
        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Demande de verification',
            'roles' => ['buyer'],
            'verification_status' => 'pending',
        ])->assertStatus(201)->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_listing_filters_by_role_and_hides_other_tenants(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);

        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Fournisseur A',
            'roles' => ['supplier'],
        ])->assertStatus(201);

        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Client A',
            'roles' => ['buyer'],
        ])->assertStatus(201);

        $this->getJson('/api/v1/trade/counterparties?role=supplier')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fournisseur A');

        // Second tenant : l'annuaire de A doit rester invisible.
        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        $this->getJson('/api/v1/trade/counterparties')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_another_tenants_counterparty_is_not_found(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);

        $id = $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Chez A',
            'roles' => ['supplier'],
        ])->json('data.id');

        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        // 404, jamais 403 : l'API ne revele pas l'existence de l'objet.
        $this->getJson("/api/v1/trade/counterparties/{$id}")->assertStatus(404);
        $this->putJson("/api/v1/trade/counterparties/{$id}", ['name' => 'Pirate'])->assertStatus(404);
        $this->deleteJson("/api/v1/trade/counterparties/{$id}")->assertStatus(404);
    }

    public function test_delete_is_logical_so_history_survives(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $id = $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Va etre archive',
            'roles' => ['supplier'],
        ])->json('data.id');

        $this->deleteJson("/api/v1/trade/counterparties/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        // La ligne EXISTE toujours : les RFQ/offres passees la citent.
        $this->assertDatabaseHas('trade_counterparties', ['id' => $id]);
        $this->assertSoftDeleted('trade_counterparties', ['id' => $id]);

        // Et elle sort du perimetre de lecture.
        $this->getJson("/api/v1/trade/counterparties/{$id}")->assertStatus(404);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('trade', true);
        $company->save();

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
