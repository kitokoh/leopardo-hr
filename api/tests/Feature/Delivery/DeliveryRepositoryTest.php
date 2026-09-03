<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Models\Delivery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-2xx — Port de persistance des livraisons (tenant-scoped).
 *
 * Vérifie le scoping tenant du repository (404 sûr hors tenant) et la
 * résolution par (source, source_reference) — zéro doublon par commande
 * source.
 */
class DeliveryRepositoryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_find_for_company_returns_delivery_and_hides_other_tenants(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $deliveryA = $this->makeDelivery($companyA, 'DLV-2026-000001');
        $this->makeDelivery($companyB, 'DLV-2026-000002');

        $repository = app(DeliveryRepositoryInterface::class);

        $found = $repository->findForCompany($deliveryA->id, (string) $companyA->id);
        self::assertNotNull($found);
        self::assertSame('DLV-2026-000001', $found->reference);

        // 404 sûr : l'id du tenant A est introuvable depuis le tenant B.
        self::assertNull($repository->findForCompany($deliveryA->id, (string) $companyB->id));
    }

    public function test_find_by_reference_is_tenant_scoped(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $this->makeDelivery($companyA, 'DLV-2026-000010');
        $this->makeDelivery($companyB, 'DLV-2026-000010');

        $repository = app(DeliveryRepositoryInterface::class);

        self::assertNotNull($repository->findByReference('DLV-2026-000010', (string) $companyA->id));
        self::assertNotNull($repository->findByReference('DLV-2026-000010', (string) $companyB->id));
        self::assertNull($repository->findByReference('DLV-2026-999999', (string) $companyA->id));
    }

    public function test_find_by_source_reference_returns_single_delivery_per_tenant_source(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        // Deux tenants peuvent référencer la même commande source (restaurant BC-25).
        $this->makeDelivery($companyA, 'DLV-2026-000020', 'restaurant', 'RST-2026-0001');
        $this->makeDelivery($companyB, 'DLV-2026-000021', 'restaurant', 'RST-2026-0001');

        $repository = app(DeliveryRepositoryInterface::class);

        self::assertNotNull($repository->findBySourceReference('restaurant', 'RST-2026-0001', (string) $companyA->id));
        self::assertNotNull($repository->findBySourceReference('restaurant', 'RST-2026-0001', (string) $companyB->id));
        self::assertNull($repository->findBySourceReference('ecommerce', 'ORD-2026-0001', (string) $companyA->id));
    }

    private function makeDelivery(
        Company $company,
        string $reference,
        string $source = 'manual',
        ?string $sourceReference = null,
    ): Delivery {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $company->id,
            'reference' => $reference,
            'source' => $source,
            'source_reference' => $sourceReference,
            'type' => 'parcel',
            'status' => 'created',
            'dropoff_contact' => 'Destinataire',
            'dropoff_address' => 'Adresse de livraison',
        ]);

        return $delivery;
    }
}
