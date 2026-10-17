<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-O-01 / BOS-O-02 / #8625 — l'intention de marché atteint le tenant.
 *
 * Ce que ce test prouve, et rien de plus :
 *
 *  1. l'intention déclarée (`market_type` + `trade_profile`) est RÉELLEMENT
 *     persistée sur le tenant — elle ne meurt plus dans le navigateur ;
 *  2. une combinaison incohérente est refusée AVANT toute écriture (aucun
 *     tenant partiel, aucun marché inventé) ;
 *  3. l'absence d'intention reste lisible (NULL), pas « domestic » par défaut —
 *     un défaut mentirait sur l'intention du visiteur ;
 *  4. la base refuse aussi une valeur hors allowlist (CHECK), pas seulement
 *     l'API : une écriture directe ne peut pas créer un marché inconnu.
 */
class MarketIntentProvisioningTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_declared_intent_is_persisted_on_the_tenant(): void
    {
        app(ProvisionGuidedTrial::class)->execute(
            email: 'negociant@exemple.test',
            companyName: 'Negoce Test',
            country: 'DZ',
            marketType: 'international',
            tradeProfile: 'importer',
        );

        /** @var Company $company */
        $company = Company::query()->where('email', 'negociant@exemple.test')->firstOrFail();

        $this->assertSame('international', $company->market_type);
        $this->assertSame('importer', $company->trade_profile);
    }

    public function test_absent_intent_stays_null_instead_of_defaulting_to_domestic(): void
    {
        app(ProvisionGuidedTrial::class)->execute(
            email: 'sansintention@exemple.test',
            companyName: 'Sans Intention',
            country: 'DZ',
        );

        /** @var Company $company */
        $company = Company::query()->where('email', 'sansintention@exemple.test')->firstOrFail();

        $this->assertNull($company->market_type);
        $this->assertNull($company->trade_profile);
    }

    public function test_incoherent_intent_is_rejected_before_any_tenant_is_created(): void
    {
        try {
            app(ProvisionGuidedTrial::class)->execute(
                email: 'incoherent@exemple.test',
                companyName: 'Incoherent Test',
                country: 'DZ',
                // Grossiste sur un marché INTERNATIONAL : chaque valeur est
                // valide isolément, le couple ne l'est pas.
                marketType: 'international',
                tradeProfile: 'wholesaler',
            );
            $this->fail('Une intention incohérente doit annuler le provisioning.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Intention de marché invalide', $e->getMessage());
        }

        $this->assertNull(
            Company::query()->where('email', 'incoherent@exemple.test')->first(),
            'Aucun tenant ne doit être créé quand l’intention est refusée.'
        );
    }

    public function test_trade_profile_without_market_is_rejected(): void
    {
        $this->assertFalse(Company::isValidMarketIntent(null, 'importer'));
    }

    public function test_market_without_profile_is_allowed(): void
    {
        // Le visiteur peut choisir son marché dans la vitrine et ne préciser
        // son profil professionnel que dans l'entretien de préparation.
        $this->assertTrue(Company::isValidMarketIntent('domestic', null));
    }

    public function test_allowlists_pair_profiles_with_their_market(): void
    {
        $this->assertTrue(Company::isValidMarketIntent('international', 'exporter'));
        $this->assertTrue(Company::isValidMarketIntent('international', 'both'));
        $this->assertTrue(Company::isValidMarketIntent('domestic', 'retailer'));
        $this->assertFalse(Company::isValidMarketIntent('domestic', 'importer'));
        $this->assertFalse(Company::isValidMarketIntent('international', 'retailer'));
        $this->assertFalse(Company::isValidMarketIntent('planetary', 'importer'));
    }

    public function test_database_rejects_market_value_outside_the_allowlist(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        try {
            \Illuminate\Support\Facades\DB::transaction(
                fn () => \Illuminate\Support\Facades\DB::table('public.companies')
                    ->where('id', $company->id)
                    ->update(['market_type' => 'planetary'])
            );
            $this->fail('Le CHECK en base doit refuser une valeur de marché inconnue.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('23514', $e->getCode(), 'Violation de CHECK attendue (23514).');
        }
    }
}
