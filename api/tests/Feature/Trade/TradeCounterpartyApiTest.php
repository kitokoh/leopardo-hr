<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use App\Modules\Trade\Domain\Enums\TradeVerificationStatus;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contreparties Trade — BC-36 TRADE, #9780.
 *
 * ## Ce que ce test protège
 *
 * `trade_counterparties` + son modèle + sa factory existaient depuis
 * TRADE-003/#8478, mais **aucune route ne les exposait** : le répertoire
 * clients/fournisseurs était un modèle orphelin, et
 * `trade_deal.trade_counterparty_id` ne pouvait jamais être renseigné.
 *
 * Les invariants verrouillés ici sont ceux qui casseraient silencieusement si
 * quelqu'un « simplifiait » le contrôleur plus tard :
 *
 *  - **isolation tenant** : la contrepartie d'une autre entreprise est 404,
 *    jamais 403 (l'API ne révèle pas son existence) ;
 *  - **vérification = acte tracé** : on ne se déclare pas `verified` en
 *    créant sa fiche, et une décision exige un motif ;
 *  - **rôles fermés** : un rôle hors registre est refusé, jamais ignoré ;
 *  - **suppression douce** : `DELETE` pose `deleted_at`, l'historique survit ;
 *  - **RBAC** : un rôle de lecture seule ne peut pas écrire.
 */
class TradeCounterpartyApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createCompany(bool $enableTrade = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableTrade) {
            $company->setFeature('trade', true);
            $company->save();
        }

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

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/trade/counterparties')->assertStatus(401);
        $this->postJson('/api/v1/trade/counterparties', [])->assertStatus(401);
    }

    public function test_endpoints_are_gated_by_the_trade_feature(): void
    {
        $company = $this->createCompany(enableTrade: false);
        $this->actingAsEmployee($company);

        $this->getJson('/api/v1/trade/counterparties')->assertStatus(403);
    }

    public function test_manager_can_create_and_list_a_counterparty(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Acier Nord SARL',
            'country' => 'FR',
            'city' => 'Lille',
            'email' => 'contact@acier-nord.example',
            'roles' => [TradeCounterpartyRole::Supplier->value, TradeCounterpartyRole::Manufacturer->value],
            'notes' => 'Fournisseur historique, délai 3 semaines.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Acier Nord SARL')
            ->assertJsonPath('data.country', 'FR')
            // Une fiche neuve n'est JAMAIS vérifiée : on ne se déclare pas
            // contrôlé en créant sa propre fiche.
            ->assertJsonPath('data.verification_status', TradeVerificationStatus::None->value)
            ->assertJsonPath('data.verified_at', null);

        $this->assertCount(2, $response->json('data.roles'));

        $this->getJson('/api/v1/trade/counterparties')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acier Nord SARL');
    }

    public function test_roles_are_required_and_closed(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Aucun rôle : une contrepartie sans rôle ne peut être ni invitée à une
        // RFQ, ni destinataire d'une offre, ni sollicitée pour un transport.
        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Sans rôle',
            'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        // Rôle hors registre : refusé, jamais ignoré silencieusement.
        $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Rôle inventé',
            'roles' => ['pirate'],
        ])->assertStatus(422)->assertJsonValidationErrors('roles.0');
    }

    public function test_duplicate_roles_are_deduplicated(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/counterparties', [
            'name' => 'Doublon SARL',
            'roles' => ['supplier', 'supplier', 'buyer'],
        ]);

        $response->assertStatus(201);

        /** @var list<string> $roles */
        $roles = $response->json('data.roles');
        $this->assertSame(['supplier', 'buyer'], $roles);
    }

    public function test_counterparty_of_another_tenant_is_not_found(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var TradeCounterparty $foreign */
        $foreign = TradeCounterparty::factory()->create(['company_id' => $otherCompany->id]);

        // 404, jamais 403 : l'API ne révèle pas l'existence de la ressource.
        $this->getJson("/api/v1/trade/counterparties/{$foreign->id}")->assertStatus(404);
        $this->putJson("/api/v1/trade/counterparties/{$foreign->id}", ['name' => 'Piraté'])->assertStatus(404);
        $this->deleteJson("/api/v1/trade/counterparties/{$foreign->id}")->assertStatus(404);
    }

    public function test_verification_is_a_traced_act_requiring_a_reason(): void
    {
        $company = $this->createCompany();
        $employee = $this->actingAsEmployee($company);

        /** @var TradeCounterparty $counterparty */
        $counterparty = TradeCounterparty::factory()->create(['company_id' => $company->id]);

        // Une décision sans motif n'est pas une vérification, c'est une case cochée.
        $this->postJson("/api/v1/trade/counterparties/{$counterparty->id}/verify", [
            'status' => 'verified',
        ])->assertStatus(422)->assertJsonValidationErrors('note');

        $response = $this->postJson("/api/v1/trade/counterparties/{$counterparty->id}/verify", [
            'status' => 'verified',
            'note' => 'Kbis et attestation fiscale contrôlés le 2026-11-20.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.verified_by_employee_id', $employee->id);

        $this->assertNotNull($response->json('data.verified_at'));

        // Retour à l'instruction : la trace de la décision précédente est effacée.
        $this->postJson("/api/v1/trade/counterparties/{$counterparty->id}/verify", [
            'status' => 'pending',
        ])->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.verified_at', null)
            ->assertJsonPath('data.verified_by_employee_id', null);
    }

    public function test_verification_is_reserved_to_the_trade_admin(): void
    {
        $company = $this->createCompany();
        // `comptable` n'obtient que le rôle `reports` : aucune opération d'écriture.
        $this->actingAsEmployee($company, 'comptable');

        /** @var TradeCounterparty $counterparty */
        $counterparty = TradeCounterparty::factory()->create(['company_id' => $company->id]);

        $this->postJson("/api/v1/trade/counterparties/{$counterparty->id}/verify", [
            'status' => 'verified',
            'note' => 'Auto-vérification tentée.',
        ])->assertStatus(403);
    }

    public function test_search_filter_escapes_sql_wildcards(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        TradeCounterparty::factory()->create(['company_id' => $company->id, 'name' => 'Alpha SARL']);
        TradeCounterparty::factory()->create(['company_id' => $company->id, 'name' => 'Beta SARL']);

        // Un `%` saisi est un CARACTÈRE, pas un joker : sans échappement, cette
        // recherche ramènerait tout le répertoire.
        $this->getJson('/api/v1/trade/counterparties?q=%25')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/trade/counterparties?q=Alpha')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha SARL');
    }

    public function test_filter_by_role_matches_multi_role_counterparties(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        TradeCounterparty::factory()->create([
            'company_id' => $company->id,
            'name' => 'Fabricant Fournisseur',
            'roles' => ['manufacturer', 'supplier'],
        ]);
        TradeCounterparty::factory()->create([
            'company_id' => $company->id,
            'name' => 'Client Seul',
            'roles' => ['buyer'],
        ]);

        // Une contrepartie multi-rôles doit ressortir dans CHAQUE filtre.
        $this->getJson('/api/v1/trade/counterparties?role=supplier')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fabricant Fournisseur');

        $this->getJson('/api/v1/trade/counterparties?role=manufacturer')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_delete_is_soft_and_preserves_history(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        /** @var TradeCounterparty $counterparty */
        $counterparty = TradeCounterparty::factory()->create(['company_id' => $company->id]);

        $this->deleteJson("/api/v1/trade/counterparties/{$counterparty->id}")->assertStatus(204);

        // La ligne survit : les dossiers et offres passés la référencent.
        $this->assertSoftDeleted('trade_counterparties', ['id' => $counterparty->id]);
        $this->getJson("/api/v1/trade/counterparties/{$counterparty->id}")->assertStatus(404);
    }
}
