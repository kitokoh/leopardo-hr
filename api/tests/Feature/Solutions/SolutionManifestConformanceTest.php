<?php

declare(strict_types=1);

namespace Tests\Feature\Solutions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-014 (#8201) — Convergence des manifests : TOUS les manifests
 * implémentent le contrat Core v2 (BOS-013) et sont enregistrés au
 * catalogue ; un seul contrat de manifest restaurant ; la révocation des
 * grants conserve les codes partagés avec une solution encore active.
 *
 * Critères d'acceptation de l'issue :
 *  1. 9 manifests conformes au contrat Core v2 et enregistrés (ce test de
 *     conformité) ;
 *  2. un seul manifest restaurant par rôle (descripteur `restaurant` /
 *     verticale opérationnelle `restaurantmanager`, même industrie) ;
 *     activation restaurant de bout en bout verte ;
 *  3. (la garde CI `check-solution-manifest-conformance.sh` couvre le
 *     critère « rouge si manifest non conforme ou non enregistré »).
 */
class SolutionManifestConformanceTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Les 9 codes de solution de l'allowlist (triés). */
    private const EXPECTED_CODES = [
        'delivery',
        'edumanager',
        'fuel_station',
        'healthmanager',
        'hospitality',
        'pharmacy',
        'restaurant',
        'restaurantmanager',
        'travelagency',
    ];

    public function test_nine_manifests_are_registered_and_conform_to_core_contract_v2(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        $this->assertSame(self::EXPECTED_CODES, $catalogue->codes());

        foreach (self::EXPECTED_CODES as $code) {
            $manifest = $catalogue->resolve($code);

            // Contrat Core v2 : instance du contrat, industrie valide,
            // description d'onboarding, permissions en map code => libellé.
            $this->assertInstanceOf(SolutionManifest::class, $manifest, "{$code} : contrat Core non implémenté");
            $this->assertSame($code, $manifest->code(), "{$code} : code() ne correspond pas à la clé d'enregistrement");
            $this->assertInstanceOf(SolutionIndustry::class, $manifest->industry(), "{$code} : industry invalide");
            $this->assertNotSame('', $manifest->name(), "{$code} : name vide");
            $this->assertContains($manifest->maturity(), ['pilot', 'production', 'placeholder'], "{$code} : maturité hors vocabulaire");
            $this->assertNotSame('', $manifest->description(), "{$code} : description vide");

            $permissions = $manifest->permissions();
            $this->assertNotSame([], $permissions, "{$code} : aucune permission déclarée");
            foreach ($permissions as $permissionCode => $label) {
                $this->assertIsString($permissionCode, "{$code} : clé de permission non string (liste legacy ?)");
                $this->assertIsString($label, "{$code} : libellé non string pour {$permissionCode}");
                $this->assertNotSame('', $label, "{$code} : libellé vide pour {$permissionCode}");
            }

            foreach (['requiredModules', 'optionalModules', 'sensitiveData'] as $method) {
                foreach ($manifest->{$method}() as $entry) {
                    $this->assertIsString($entry, "{$code} : entrée non string dans {$method}()");
                }
            }
        }
    }

    public function test_single_restaurant_industry_across_descriptor_and_operational_codes(): void
    {
        $catalogue = app(SolutionCatalogue::class);

        // Un seul manifest restaurant par rôle (BOS-014) : le descripteur
        // d'onboarding `restaurant` et la verticale opérationnelle
        // `restaurantmanager` sont deux codes enregistrés de la MÊME
        // industrie — il n'y a plus de contrat local dupliqué.
        $this->assertSame(SolutionIndustry::Restaurant, $catalogue->resolve('restaurant')->industry());
        $this->assertSame(SolutionIndustry::Restaurant, $catalogue->resolve('restaurantmanager')->industry());
    }

    public function test_restaurant_activation_end_to_end_and_revoke_keeps_shared_codes(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['attendance' => true, 'documents' => true, 'notifications' => true],
        ]);
        Employee::factory()->manager()->create(['company_id' => $company->id]);

        $activator = app(SolutionActivator::class);

        // Activation restaurant de bout en bout : le code descripteur pose
        // aussi le flag opérationnel (cascade du listener) ET installe les
        // permissions des DEUX manifests (activator + cascade idempotente).
        $this->assertSame('activated', $activator->activate($company, 'restaurant')['status']);

        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurant'));
        $this->assertTrue($company->hasFeature('restaurantmanager'));

        $keys = $this->grantKeys($company);
        $this->assertContains('restaurant.manager', $keys); // partagé descripteur + verticale
        $this->assertContains('restaurant.manage', $keys); // verticale opérationnelle

        // Désactivation manuelle de la verticale opérationnelle : le code
        // partagé avec le descripteur encore ACTIF est conservé, les autres
        // grants de la verticale sont retirés proprement.
        $this->assertSame('deactivated', $activator->deactivate($company, 'restaurantmanager')['status']);

        $keys = $this->grantKeys($company);
        $this->assertContains('restaurant.manager', $keys);
        $this->assertNotContains('restaurant.manage', $keys);
        $this->assertNotContains('restaurant.kitchen', $keys);
        $this->assertFalse($company->refresh()->hasFeature('restaurantmanager'));
    }

    /** @return list<string> */
    private function grantKeys(Company $company): array
    {
        /** @var list<string> $keys */
        $keys = EmployeeModuleGrant::forCompany($company)
            ->orderBy('module_key')
            ->pluck('module_key')
            ->all();

        return $keys;
    }
}
