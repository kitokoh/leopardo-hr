<?php

declare(strict_types=1);

namespace Tests\Feature\Solutions;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\Exceptions\SolutionNotFoundException;
use App\Core\Solutions\SolutionCatalogue;
use Tests\TestCase;

/**
 * BOS-C-02 (#8631, ADR-04) — un seul code canonique par verticale.
 *
 * Le catalogue résout les alias historiques vers le code canonique, n'expose
 * qu'un code par verticale (`canonicalCodes()`) et reste fail-closed sur un
 * code inconnu. Aucun alias ne peut pointer vers un code inexistant.
 */
class SolutionCatalogueAliasTest extends TestCase
{
    public function test_alias_resolves_to_the_canonical_manifest(): void
    {
        $catalogue = new SolutionCatalogue;
        $catalogue->register('canonical_x', $this->manifestFactory('canonical_x'), aliases: ['legacy_x']);

        $this->assertSame('canonical_x', $catalogue->resolve('legacy_x')->code());
        $this->assertSame('canonical_x', $catalogue->canonicalCode('legacy_x'));
        $this->assertSame('canonical_x', $catalogue->canonicalCode('canonical_x'));
    }

    public function test_unknown_alias_is_refused_fail_closed(): void
    {
        $catalogue = new SolutionCatalogue;
        $catalogue->register('canonical_x', $this->manifestFactory('canonical_x'), aliases: ['legacy_x']);

        $this->expectException(SolutionNotFoundException::class);

        $catalogue->resolve('legacy_unknown');
    }

    public function test_unknown_code_is_refused_by_canonical_code(): void
    {
        $catalogue = new SolutionCatalogue;

        $this->expectException(SolutionNotFoundException::class);

        $catalogue->canonicalCode('nope');
    }

    public function test_public_catalogue_exposes_one_code_per_vertical(): void
    {
        $catalogue = new SolutionCatalogue;
        $catalogue->register('canonical_x', $this->manifestFactory('canonical_x'), aliases: ['legacy_x']);

        $this->assertSame(['canonical_x'], $catalogue->canonicalCodes());
        $this->assertSame(['canonical_x'], $catalogue->codes());
        $this->assertSame(['legacy_x' => 'canonical_x'], $catalogue->aliases());
        $this->assertTrue($catalogue->has('legacy_x'));
    }

    public function test_real_catalogue_declares_restaurant_alias_and_is_consistent(): void
    {
        /** @var SolutionCatalogue $catalogue */
        $catalogue = app(SolutionCatalogue::class);

        // ADR-04 : `restaurantmanager` est le code canonique de la verticale
        // restaurant ; `restaurant` (descripteur d'onboarding historique) est
        // un alias — les statistiques ne comptent plus deux codes.
        $this->assertSame('restaurantmanager', $catalogue->canonicalCode('restaurant'));

        // Cohérence : chaque alias pointe vers un code canonique existant.
        foreach ($catalogue->aliases() as $alias => $canonical) {
            $this->assertNotSame($alias, $canonical);
            $this->assertTrue(
                $catalogue->has($canonical),
                "L'alias `{$alias}` pointe vers `{$canonical}` qui n'existe pas.",
            );
        }

        // Le catalogue public n'expose pas deux codes pour la même offre.
        $this->assertNotContains('restaurant', $catalogue->canonicalCodes());
        $this->assertContains('restaurantmanager', $catalogue->canonicalCodes());
    }

    private function manifestFactory(string $code): callable
    {
        return static fn (): SolutionManifest => new class($code) implements SolutionManifest
        {
            public function __construct(private readonly string $code) {}

            public function code(): string
            {
                return $this->code;
            }

            public function name(): string
            {
                return 'Test';
            }

            public function maturity(): string
            {
                return 'pilot';
            }

            public function industry(): SolutionIndustry
            {
                return SolutionIndustry::Restaurant;
            }

            public function description(): string
            {
                return 'Test';
            }

            /** @return list<string> */
            public function requiredModules(): array
            {
                return [];
            }

            /** @return list<string> */
            public function optionalModules(): array
            {
                return [];
            }

            /** @return list<string> */
            public function sensitiveData(): array
            {
                return [];
            }

            /** @return array<string, string> */
            public function permissions(): array
            {
                return [];
            }
        };
    }
}
