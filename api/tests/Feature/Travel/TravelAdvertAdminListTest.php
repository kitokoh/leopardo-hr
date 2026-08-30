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
 * TRAVEL-915 (#6428) — Liste gestion des annonces (tous statuts).
 *
 * GET /travel/adverts pour un rôle manager expose toutes les annonces du
 * tenant (soumission, paiement, validation/rejet, renouvellement) avec
 * statut, libellés type/position, référence de paiement, motif de rejet et
 * dates du cycle de vie — le contrat public (visible uniquement) reste
 * inchangé.
 */
class TravelAdvertAdminListTest extends TestCase
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
    private function seedReferentials(): array
    {
        return $this->tenants->withinTenant($this->company, function (): array {
            $type = TravelAdvertType::factory()->create(['code' => 'display', 'label' => 'Affichage']);
            $position = TravelAdvertPosition::factory()->create(['code' => 'hero', 'label' => 'Héro']);

            return ['type' => $type, 'position' => $position];
        });
    }

    private function createAdvert(AdvertStatus $status, ?TravelAdvertType $type = null, ?TravelAdvertPosition $position = null): TravelAdvert
    {
        return $this->tenants->withinTenant($this->company, function () use ($status, $type, $position): TravelAdvert {
            return TravelAdvert::factory()->create([
                'advert_type_id' => $type?->id,
                'advert_position_id' => $position?->id,
                'status' => $status->value,
                'paid_at' => in_array($status, [AdvertStatus::PAID, AdvertStatus::VALIDATED, AdvertStatus::EXPIRED], true) ? now() : null,
                'payment_reference' => in_array($status, [AdvertStatus::PAID, AdvertStatus::VALIDATED, AdvertStatus::EXPIRED], true) ? 'ADV-2026-0001' : null,
                'validated_at' => in_array($status, [AdvertStatus::VALIDATED, AdvertStatus::EXPIRED], true) ? now() : null,
                'rejected_reason' => $status === AdvertStatus::REJECTED ? 'Contenu non conforme' : null,
                'expires_at' => $status === AdvertStatus::EXPIRED ? now()->subDay() : null,
            ]);
        });
    }

    public function test_manager_sees_all_statuses_with_references(): void
    {
        $this->actingManager();
        ['type' => $type, 'position' => $position] = $this->seedReferentials();

        $submitted = $this->createAdvert(AdvertStatus::SUBMITTED, $type, $position);
        $rejected = $this->createAdvert(AdvertStatus::REJECTED, $type, $position);

        $data = $this->getJson('/api/v1/travel/adverts')->assertOk()->json('data');

        $byId = collect($data)->keyBy('id');
        self::assertTrue($byId->has($submitted->id), 'annonce soumise visible en gestion');
        self::assertSame('submitted', $byId[$submitted->id]['status']);
        self::assertSame('Affichage', $byId[$submitted->id]['advert_type'], 'libellé du type exposé');
        self::assertSame('Héro', $byId[$submitted->id]['advert_position'], 'libellé de la position exposé');
        self::assertNull($byId[$submitted->id]['payment_reference']);

        self::assertTrue($byId->has($rejected->id), 'annonce rejetée visible en gestion');
        self::assertSame('rejected', $byId[$rejected->id]['status']);
        self::assertSame('Contenu non conforme', $byId[$rejected->id]['rejected_reason'], 'motif de rejet exposé');
    }

    public function test_manager_list_supports_status_filter(): void
    {
        $this->actingManager();

        $submitted = $this->createAdvert(AdvertStatus::SUBMITTED);
        $paid = $this->createAdvert(AdvertStatus::PAID);

        $data = $this->getJson('/api/v1/travel/adverts?status=paid')->assertOk()->json('data');
        $ids = collect($data)->pluck('id')->all();

        self::assertContains($paid->id, $ids);
        self::assertNotContains($submitted->id, $ids);
    }

    public function test_public_contract_unchanged_for_non_manager(): void
    {
        $this->actingAgent();

        $submitted = $this->createAdvert(AdvertStatus::SUBMITTED);
        $validated = $this->createAdvert(AdvertStatus::VALIDATED);

        $data = $this->getJson('/api/v1/travel/adverts')->assertOk()->json('data');
        $ids = collect($data)->pluck('id')->all();

        self::assertContains($validated->id, $ids);
        self::assertNotContains($submitted->id, $ids, 'un agent non-manager ne voit que les annonces visibles');
    }
}
