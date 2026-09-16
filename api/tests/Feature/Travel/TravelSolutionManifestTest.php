<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Solutions\Exceptions\SolutionNotFoundException;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7220-bis (audit 2026-09-14) — la verticale Agence de voyage doit être
 * DEMANDABLE au signup self-service et ACTIVABLE par le provisioning.
 *
 * Constat : `TravelAgencyServiceProvider` n'enregistrait pas son manifest au
 * `SolutionCatalogue` (contrairement à Restaurant/FuelStation/EduManager) et
 * le manifest implémentait un contrat local au module, incompatible avec le
 * contrat core (`description()` absente, `permissions()` en liste).
 *
 * Conséquence : `POST /api/v1/trial/signup` avec `solutions:["travelagency"]`
 * répondait 422 `INVALID_SOLUTION`, `VerifyTrialSignup` refusait au verify et
 * `leopardo:solution:activate travelagency` levait une exception — un
 * propriétaire d'agence de voyage restait sans module métier.
 *
 * Contrat : `travelagency` est dans l'allowlist du catalogue, le manifest
 * résolu respecte le contrat core (description + permissions map), et
 * l'activation pose le flag de façon idempotente.
 */
class TravelSolutionManifestTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, bool>  $features
     */
    private function company(array $features = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'CM',
            'currency' => 'XAF',
            'features' => $features,
        ]);

        return $company;
    }

    public function test_travelagency_manifest_is_registered_in_catalogue(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertTrue(
            $catalogue->has('travelagency'),
            'travelagency doit être enregistré au SolutionCatalogue, sinon le signup self-service le refuse en 422.',
        );
        $this->assertContains('travelagency', $catalogue->codes());

        $manifest = $catalogue->resolve('travelagency');

        $this->assertInstanceOf(TravelAgencyManifest::class, $manifest);
        $this->assertSame('travelagency', $manifest->code());
        $this->assertSame('pilot', $manifest->maturity());
        $this->assertContains('rh', $manifest->requiredModules());
    }

    /**
     * Le contrat core expose `description()` et une map de permissions :
     * `SolutionSurveyController` appelle `description()` sur tout manifest
     * résolu — un manifest incompatible le ferait échouer en fatal.
     */
    public function test_travelagency_manifest_honours_core_contract(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('travelagency');

        $this->assertNotSame('', $manifest->description());

        $permissions = $manifest->permissions();
        $this->assertNotEmpty($permissions);
        $this->assertArrayHasKey('travel.manage', $permissions);
        // `assertIsString()` sur une valeur déjà typée `string` est tautologique
        // (PHPStan strict : « will always evaluate to true ») — on vérifie le
        // CONTENU, qui est ce qui porte le contrat de la permission.
        $this->assertNotEmpty($permissions['travel.manage']);
    }

    public function test_travelagency_is_absent_from_catalogue_allowlist_when_unknown_code(): void
    {
        $this->expectException(SolutionNotFoundException::class);

        app(SolutionCatalogue::class)->resolve('travelagency_inexistant');
    }

    public function test_travelagency_activation_is_idempotent_and_sets_flag(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true, 'crm' => true]);
        $activator = app(SolutionActivator::class);

        $this->assertFalse($company->hasFeature('travelagency'));

        $first = $activator->activate($company, 'travelagency');
        $this->assertSame('activated', $first['status']);

        $company->refresh();
        $this->assertTrue($company->hasFeature('travelagency'));

        $second = $activator->activate($company, 'travelagency');
        $this->assertSame('already_active', $second['status']);
    }

    public function test_travelagency_activation_refuses_missing_dependencies(): void
    {
        // Tenant frais : seul `rh` est actif, `crm` manque au manifest.
        $company = $this->company(['rh' => true]);

        $this->expectException(SolutionMissingDependencyException::class);

        try {
            app(SolutionActivator::class)->activate($company, 'travelagency');
        } catch (SolutionMissingDependencyException $exception) {
            $company->refresh();
            $this->assertFalse($company->hasFeature('travelagency'));

            throw $exception;
        }
    }

    /**
     * Chemin de provisioning BC-25 (#6693) : `activateWithDependencies` active
     * d'abord les modules requis du manifest, puis la solution — c'est le
     * chemin emprunté par le verify du signup self-service.
     */
    public function test_activate_with_dependencies_bootstraps_fresh_tenant(): void
    {
        $company = $this->company(['rh' => true]);

        $result = app(SolutionActivator::class)->activateWithDependencies($company, 'travelagency');
        $this->assertSame('activated', $result['status']);

        $company->refresh();
        $this->assertTrue($company->hasFeature('travelagency'));
        $this->assertTrue($company->hasFeature('crm'));
        $this->assertTrue($company->hasFeature('notifications'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.dependencies_activated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_travelagency_flag_is_fail_closed_by_default(): void
    {
        $company = $this->company();

        $this->assertFalse($company->hasFeature('travelagency'));
        $this->assertFalse(FeatureFlag::for($company)['travelagency'] ?? false);
    }

    /**
     * Audit 2026-09-14 — activer la solution ne suffit pas : une agence de
     * voyage sans référentiel géographique ne peut créer AUCUN trajet (une
     * route exige une ville d'origine et une ville d'arrivée). Le module doit
     * amorcer pays + villes à l'activation, via `SolutionActivated`.
     */
    public function test_activating_travelagency_seeds_geographic_referential(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true, 'crm' => true]);

        app(SolutionActivator::class)->activate($company, 'travelagency');

        app(TenantManager::class)->setTenant($company);

        try {
            $countries = TravelCountry::query()->where('company_id', $company->id)->count();
            $cities = TravelCity::query()->where('company_id', $company->id)->count();
        } finally {
            app(TenantManager::class)->resetToPrevious();
        }

        $this->assertGreaterThan(0, $countries, 'Le référentiel pays doit être seedé à l\'activation.');
        $this->assertGreaterThan(0, $cities, 'Le référentiel villes doit être seedé — sans quoi aucun trajet A→B n\'est créable.');
    }

    /**
     * L'amorçage doit rester idempotent : une seconde activation ne duplique
     * rien (`SolutionActivator` renvoie `already_active`, donc aucun événement
     * n'est émis ; le seeder lui-même est en `insertOrIgnore`).
     */
    public function test_travelagency_seeding_is_idempotent(): void
    {
        $company = $this->company(['rh' => true, 'documents' => true, 'notifications' => true, 'crm' => true]);
        $activator = app(SolutionActivator::class);

        $activator->activate($company, 'travelagency');
        $activator->activateWithDependencies($company->refresh(), 'travelagency');
        app(ActivateTravelAgencyAction::class)->execute($company->refresh());

        app(TenantManager::class)->setTenant($company);

        try {
            $cities = TravelCity::query()->where('company_id', $company->id)
                ->where('name', 'Douala')
                ->where('country_iso2', 'CM')
                ->count();
        } finally {
            app(TenantManager::class)->resetToPrevious();
        }

        $this->assertSame(1, $cities, 'Aucun doublon de ville après réactivations successives.');
    }
}
