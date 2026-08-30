<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-915 (#6428) — Liste admin des annonces (tous statuts) : pilotage du
 * cycle soumission → paiement → validation → renouvellement depuis l'admin.
 */
class TravelAdvertAdminApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function actingAgent(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'agent',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @return array{type: TravelAdvertType, position: TravelAdvertPosition}
     */
    private function seedReferences(): array
    {
        return $this->tenants->withinTenant($this->company, function (): array {
            $type = TravelAdvertType::factory()->create(['label' => 'Bannière']);
            $position = TravelAdvertPosition::factory()->create(['label' => 'Accueil']);

            return ['type' => $type, 'position' => $position];
        });
    }

    public function test_admin_index_requires_manager_role(): void
    {
        $this->actingAgent();

        $this->getJson('/api/v1/travel/adverts/admin')
            ->assertStatus(403);
    }

    public function test_admin_index_returns_all_statuses_with_labels(): void
    {
        $this->actingManager();
        $refs = $this->seedReferences();

        $this->tenants->withinTenant($this->company, function () use ($refs): void {
            TravelAdvert::factory()->create([
                'advert_type_id' => $refs['type']->id,
                'advert_position_id' => $refs['position']->id,
                'title' => 'Annonce soumise',
                'status' => AdvertStatus::SUBMITTED->value,
            ]);
            TravelAdvert::factory()->create([
                'advert_type_id' => $refs['type']->id,
                'advert_position_id' => $refs['position']->id,
                'title' => 'Annonce payée',
                'status' => AdvertStatus::PAID->value,
            ]);
            TravelAdvert::factory()->create([
                'advert_type_id' => $refs['type']->id,
                'advert_position_id' => $refs['position']->id,
                'title' => 'Annonce rejetée',
                'status' => AdvertStatus::REJECTED->value,
                'rejected_reason' => 'Contenu non conforme',
            ]);
        });

        $response = $this->getJson('/api/v1/travel/adverts/admin')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $titles = collect($response->json('data'))->pluck('title');
        self::assertTrue($titles->contains('Annonce soumise'), 'submitted visible en admin');
        self::assertTrue($titles->contains('Annonce payée'), 'paid visible en admin');
        self::assertTrue($titles->contains('Annonce rejetée'), 'rejected visible en admin');

        $rejected = collect($response->json('data'))->firstWhere('title', 'Annonce rejetée');
        self::assertSame('rejected', $rejected['status']);
        self::assertSame('Contenu non conforme', $rejected['rejected_reason']);
        self::assertSame('Bannière', $rejected['advert_type']);
        self::assertSame('Accueil', $rejected['advert_position']);
        self::assertFalse($rejected['visible']);
    }

    public function test_admin_index_filters_by_status(): void
    {
        $this->actingManager();

        $this->tenants->withinTenant($this->company, function (): void {
            TravelAdvert::factory()->create(['status' => AdvertStatus::SUBMITTED->value]);
            TravelAdvert::factory()->create(['status' => AdvertStatus::PAID->value]);
        });

        $this->getJson('/api/v1/travel/adverts/admin?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_admin_index_is_tenant_isolated(): void
    {
        $this->actingManager();

        $this->tenants->withinTenant($this->company, function (): void {
            TravelAdvert::factory()->create(['status' => AdvertStatus::SUBMITTED->value]);
        });

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($other, function (): void {
            TravelAdvert::factory()->create(['status' => AdvertStatus::SUBMITTED->value]);
        });

        $this->getJson('/api/v1/travel/adverts/admin')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
