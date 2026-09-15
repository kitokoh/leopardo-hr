<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7393 — `ActivateTravelAgencyAction` doit PERSISTER le flag `travelagency`.
 *
 * Constat de recette (2026-09-14, tenant agence de voyage) : l'action appelait
 * `Company::setFeature('travelagency', true)` — qui ne mute que l'instance en
 * mémoire — sans jamais `save()`. Résultat :
 *
 *   $ php artisan leopardo:travel:activate <company>
 *   « Verticale TravelAgency activée pour … »          ← mensonge
 *
 *   GET /api/v1/travel/countries
 *   → 403 FEATURE_NOT_ENABLED
 *
 * Le référentiel géographique, lui, était bien seedé (écriture directe en base) :
 * l'activation était donc faite « à moitié ».
 *
 * Pourquoi les tests existants ne l'ont pas vu :
 *  - `TravelAgencyModuleRegistryTest` teste le chemin MODÈLE
 *    (`setFeature()` + `save()` écrit à la main dans le test) ;
 *  - `TravelGeoSeedQualityTest` exerce bien l'Action… mais n'assert que le seed
 *    géographique, jamais le flag.
 *
 * Ce test exerce l'Action et vérifie l'état **relu depuis la base**.
 */
class TravelAgencyActivationPersistsFlagTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    public function test_action_persists_travelagency_flag_in_database(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            app(ActivateTravelAgencyAction::class)->execute($this->company);
        });

        // Relecture depuis la base : on ne fait JAMAIS confiance à l'instance
        // en mémoire, c'est précisément le piège qui a produit #7393.
        $fresh = Company::query()->findOrFail($this->company->id);

        self::assertTrue(
            $fresh->hasFeature('travelagency'),
            'companies.features.travelagency doit être persisté après ActivateTravelAgencyAction::execute().',
        );

        self::assertTrue(
            FeatureFlag::for($fresh)['travelagency'],
            'Le flag résolu doit exposer travelagency = true (sinon l’admin plateforme le voit à false).',
        );
    }

    public function test_action_is_idempotent_on_the_flag(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $action = app(ActivateTravelAgencyAction::class);
            $action->execute($this->company);
            $action->execute($this->company);
        });

        $fresh = Company::query()->findOrFail($this->company->id);

        self::assertTrue($fresh->hasFeature('travelagency'));
        self::assertTrue($fresh->features['travelagency'] ?? false);
    }
}
