<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Exceptions\DuplicateSurfaceProfileException;
use App\Modules\Branding\Domain\Surface\Profiles\FuelSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\HealthSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\HospitalitySurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\PharmacySurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\RestaurantSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\RetailSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\TicketingSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\TradeSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\TravelSurfaceProfile;
use App\Modules\Branding\Domain\Surface\Profiles\VtcSurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileRegistry;
use PHPUnit\Framework\TestCase;

/**
 * #9579 — le registre de profils de surface.
 *
 * Le point critique : une verticale inconnue ne doit JAMAIS produire une
 * surface vide, et deux providers sur la même verticale doivent échouer au
 * boot plutôt que de laisser le dernier gagner silencieusement.
 */
final class SurfaceProfileRegistryTest extends TestCase
{
    /**
     * @return list<\App\Modules\Branding\Domain\Surface\SurfaceProfileProvider>
     */
    private function allProviders(): array
    {
        return [
            new RestaurantSurfaceProfile,
            new RetailSurfaceProfile,
            new PharmacySurfaceProfile,
            new TravelSurfaceProfile,
            new VtcSurfaceProfile,
            new FuelSurfaceProfile,
            new HospitalitySurfaceProfile,
            new HealthSurfaceProfile,
            new TradeSurfaceProfile,
            new TicketingSurfaceProfile,
        ];
    }

    public function test_generic_profile_always_exists_even_without_providers(): void
    {
        $registry = SurfaceProfileRegistry::build([]);

        $this->assertTrue($registry->has(BrandVertical::Generic->value));
        $this->assertSame('generic', $registry->forVertical('anything')->vertical);
    }

    public function test_all_ten_verticals_are_registered(): void
    {
        $registry = SurfaceProfileRegistry::build($this->allProviders());

        $this->assertCount(11, $registry->all()); // 10 + generic
        $this->assertCount(10, $registry->specialisedVerticals());
    }

    public function test_unknown_vertical_falls_back_to_generic(): void
    {
        $registry = SurfaceProfileRegistry::build($this->allProviders());

        $profile = $registry->forVertical('not-a-vertical');

        $this->assertSame('generic', $profile->vertical);
        $this->assertFalse($registry->has('not-a-vertical'));
    }

    public function test_duplicate_vertical_is_rejected_at_boot(): void
    {
        $this->expectException(DuplicateSurfaceProfileException::class);
        $this->expectExceptionMessage('SURFACE_PROFILE_DUPLICATE');

        SurfaceProfileRegistry::build([
            new RestaurantSurfaceProfile,
            new RestaurantSurfaceProfile,
        ]);
    }

    public function test_generic_cannot_be_overridden(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SURFACE_PROFILE_GENERIC_IS_RESERVED');

        SurfaceProfileRegistry::build([
            new class implements \App\Modules\Branding\Domain\Surface\SurfaceProfileProvider
            {
                public function vertical(): string
                {
                    return 'generic';
                }

                public function profile(): SurfaceProfile
                {
                    return new SurfaceProfile(
                        vertical: 'generic',
                        label: 'Pirate',
                        capabilities: [],
                        blockTypes: [],
                        theme: [],
                    );
                }
            },
        ]);
    }

    public function test_unknown_capability_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SURFACE_PROFILE_UNKNOWN_CAPABILITY');

        new SurfaceProfile(
            vertical: 'restaurant',
            label: 'Test',
            capabilities: ['teleport'],
            blockTypes: [],
            theme: [],
        );
    }

    public function test_primary_cta_must_be_a_declared_capability(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SURFACE_PROFILE_PRIMARY_CTA_NOT_DECLARED');

        new SurfaceProfile(
            vertical: 'restaurant',
            label: 'Test',
            capabilities: [SurfaceCapability::Contact->value],
            blockTypes: [],
            theme: [],
            primaryCta: SurfaceCapability::Order->value,
        );
    }

    /**
     * Chaque profil doit être cohérent : son CTA primaire est déclaré, et sa
     * verticale correspond à celle du provider.
     */
    public function test_every_profile_is_internally_consistent(): void
    {
        foreach ($this->allProviders() as $provider) {
            $profile = $provider->profile();

            $this->assertSame($provider->vertical(), $profile->vertical);
            $this->assertNotSame('', $profile->label);
            $this->assertNotSame([], $profile->blockTypes);

            if ($profile->primaryCta !== null) {
                $this->assertTrue(
                    $profile->hasCapability($profile->primaryCta),
                    sprintf('Le CTA primaire de %s n\'est pas une capacité déclarée.', $profile->vertical),
                );
            }
        }
    }

    /**
     * Le B2B ne doit pas promettre de commande en un clic : son parcours est
     * le devis. C'est une décision produit, donc un test.
     */
    public function test_trade_profile_does_not_promise_online_order(): void
    {
        $profile = (new TradeSurfaceProfile)->profile();

        $this->assertFalse($profile->hasCapability(SurfaceCapability::Order->value));
        $this->assertSame(SurfaceCapability::Quote->value, $profile->primaryCta);
    }

    /**
     * La pharmacie met l'ordonnance en avant, pas la vente.
     */
    public function test_pharmacy_profile_prioritises_prescription(): void
    {
        $profile = (new PharmacySurfaceProfile)->profile();

        $this->assertSame(SurfaceCapability::Prescription->value, $profile->primaryCta);
    }

    public function test_registry_all_returns_a_copy(): void
    {
        $registry = SurfaceProfileRegistry::build($this->allProviders());

        $copy = $registry->all();
        $copy['restaurant'] = SurfaceProfile::generic();

        $this->assertSame('restaurant', $registry->forVertical('restaurant')->vertical);
    }
}
