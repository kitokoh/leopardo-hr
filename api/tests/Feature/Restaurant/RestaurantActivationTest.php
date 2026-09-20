<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Application\Actions\ActivateRestaurantManagerAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-105 (#6162) — Activation tenant de la verticale RestaurantManager.
 *
 * `ActivateRestaurantManagerAction` : active le feature flag
 * `restaurantmanager` et seede le référentiel de base (branche par défaut,
 * unités, taxes, catégories). Rejouer l'activation est idempotent — le
 * kill switch reste opérationnel (désactiver le flag → 403 middleware).
 */
class RestaurantActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_activation_enables_flag_and_seeds_referential(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $this->assertFalse($company->hasFeature('restaurantmanager'));

        app(ActivateRestaurantManagerAction::class)->execute($company);

        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurantmanager'));

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(1, RestaurantBranch::query()->where('code', 'MAIN')->count());
            $this->assertSame(4, RestaurantUnit::query()->count());
            $this->assertSame(2, RestaurantTaxRate::query()->count());
            $this->assertSame(4, RestaurantCategory::query()->count());
        });
    }

    public function test_activation_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(ActivateRestaurantManagerAction::class)->execute($company);
        app(ActivateRestaurantManagerAction::class)->execute($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(1, RestaurantBranch::query()->where('code', 'MAIN')->count());
            $this->assertSame(4, RestaurantUnit::query()->count());
            $this->assertSame(2, RestaurantTaxRate::query()->count());
            $this->assertSame(4, RestaurantCategory::query()->count());
        });
    }

    /**
     * #7976 — l'activation STANDARD d'une verticale passe par
     * `SolutionActivator` avec le code de solution `restaurant`, qui ne pose
     * que le flag `restaurant` : sans le listener `SolutionActivated` du
     * module, les 210 routes gatées par `module.restaurantmanager` restaient
     * en 403. Contrat : l'activation standard pose AUSSI `restaurantmanager`.
     */
    public function test_standard_solution_activation_also_enables_restaurantmanager_flag(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        foreach (['rh', 'attendance', 'documents', 'notifications'] as $module) {
            $company->setFeature($module, true);
        }
        $company->save();

        app(SolutionActivator::class)->activate($company, 'restaurant');

        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurant'));
        $this->assertTrue(
            $company->hasFeature('restaurantmanager'),
            'L\'activation standard de la solution restaurant doit aussi poser le flag restaurantmanager (#7976).',
        );
    }

    public function test_activate_command_resolves_company_by_slug(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'resto-activation-test',
            'country' => 'CM',
            'currency' => 'XAF',
        ]);

        $this->artisan('leopardo:restaurant:activate', ['company' => 'resto-activation-test'])
            ->assertSuccessful();

        $company->refresh();
        $this->assertTrue($company->hasFeature('restaurantmanager'));
    }
}
