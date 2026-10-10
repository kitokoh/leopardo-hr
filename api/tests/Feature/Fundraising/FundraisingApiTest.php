<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Verticale FUNDRAISING — API privée de gestion : CRUD cagnottes, cycle de
 * vie (publish/pause/close) avec maintien de l'annuaire public, RBAC
 * (principal/rh uniquement) et feature flag `fundraising` fail-closed.
 */
class FundraisingApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(bool $withFeature = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);

        if ($withFeature) {
            $company->setFeature('fundraising', true);
            $company->save();
        }

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Aide pour Fatou',
            'description' => 'Frais médicaux urgents.',
            'beneficiary_name' => 'Fatou Ndiaye',
            'beneficiary_contact' => '+221770000000',
            'category' => 'medical',
            'goal_amount' => 500000,
            'currency' => 'XOF',
            'suggested_amounts' => [5000, 10000, 25000],
        ], $overrides);
    }

    public function test_crud_and_generated_slug(): void
    {
        $company = $this->company();
        $this->principal($company);

        $response = $this->postJson('/api/v1/fundraising/fundraisers', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Aide pour Fatou')
            ->assertJsonPath('data.status', 'draft');

        $this->assertEquals(0.0, (float) $response->json('data.collected_amount'));
        $this->assertEquals(0.0, (float) $response->json('data.available_balance'));

        $slug = $response->json('data.slug');
        $this->assertIsString($slug);
        $this->assertMatchesRegularExpression('/^aide-pour-fatou-[a-z0-9]+$/', (string) $slug);

        $id = $response->json('data.id');

        $this->getJson('/api/v1/fundraising/fundraisers')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/fundraising/fundraisers/'.$id)
            ->assertOk()
            ->assertJsonPath('data.beneficiary_name', 'Fatou Ndiaye');

        $this->putJson('/api/v1/fundraising/fundraisers/'.$id, ['title' => 'Aide pour Fatou Ndiaye'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Aide pour Fatou Ndiaye')
            ->assertJsonPath('data.slug', $slug); // le slug ne change jamais
    }

    public function test_validation_rejects_bad_payload(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/fundraising/fundraisers', ['title' => ''])
            ->assertStatus(422);

        $this->postJson('/api/v1/fundraising/fundraisers', $this->payload(['category' => 'unknown']))
            ->assertStatus(422);
    }

    public function test_publish_pause_close_maintains_public_directory(): void
    {
        $company = $this->company();
        $this->principal($company);

        $id = $this->postJson('/api/v1/fundraising/fundraisers', $this->payload())->json('data.id');
        $slug = $this->postJson('/api/v1/fundraising/fundraisers', $this->payload(['title' => 'Autre cagnotte']))->json('data.slug');

        // draft : pas d'entrée d'annuaire.
        /** @var Fundraiser $firstFundraiser */
        $firstFundraiser = Fundraiser::query()->findOrFail($id);
        $firstSlug = $firstFundraiser->slug;
        $this->assertNull(FundraiserPublicLink::query()->where('slug', $firstSlug)->first());

        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertNotNull(FundraiserPublicLink::query()->where('slug', $firstSlug)->first());

        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/pause')
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        // pause : l'entrée est retirée (404 public).
        $this->assertNull(FundraiserPublicLink::query()->where('slug', $firstSlug)->first());

        // re-publication puis clôture : `closed` reste lisible publiquement.
        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/publish')->assertOk();
        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame('closed', FundraiserPublicLink::query()->where('slug', $firstSlug)->value('status'));

        // Transition invalide : closed → publish = 422 INVALID_STATUS_TRANSITION.
        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/publish')
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_STATUS_TRANSITION');

        // Le slug de la seconde cagnotte reste sans entrée (jamais publiée).
        $this->assertNull(FundraiserPublicLink::query()->where('slug', $slug)->first());
    }

    public function test_cancel_requires_zero_collected(): void
    {
        $company = $this->company();
        $this->principal($company);

        // Brouillon → annulation OK, entrée d'annuaire absente.
        $id = $this->postJson('/api/v1/fundraising/fundraisers', $this->payload())->json('data.id');

        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Annulée = état final : aucune re-publication.
        $this->postJson('/api/v1/fundraising/fundraisers/'.$id.'/publish')
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_STATUS_TRANSITION');

        // Collecte non nulle → annulation refusée (remboursements phase 2).
        $id2 = $this->postJson('/api/v1/fundraising/fundraisers', $this->payload(['title' => 'Avec collecte']))->json('data.id');
        \App\Modules\Fundraising\Domain\Models\Fundraiser::query()->whereKey($id2)->update(['collected_amount' => 100]);

        $this->postJson('/api/v1/fundraising/fundraisers/'.$id2.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_STATUS_TRANSITION');
    }

    public function test_feature_flag_fail_closed(): void
    {
        $company = $this->company(false); // verticale NON activée
        $this->principal($company);

        $this->getJson('/api/v1/fundraising/fundraisers')->assertStatus(403);
        $this->postJson('/api/v1/fundraising/fundraisers', $this->payload())->assertStatus(403);
    }

    public function test_rbac_denies_plain_employee(): void
    {
        $company = $this->company();
        $this->employee($company);

        $this->postJson('/api/v1/fundraising/fundraisers', $this->payload())->assertStatus(403);
    }

    public function test_cross_tenant_isolation(): void
    {
        $companyA = $this->company();
        $this->principal($companyA);
        $id = $this->postJson('/api/v1/fundraising/fundraisers', $this->payload())->json('data.id');

        // Un responsable d'un AUTRE tenant ne voit ni ne modifie la cagnotte.
        $companyB = $this->company();
        $this->principal($companyB);

        $this->getJson('/api/v1/fundraising/fundraisers/'.$id)->assertStatus(404);
        $this->putJson('/api/v1/fundraising/fundraisers/'.$id, ['title' => 'piraté'])->assertStatus(404);
    }
}
