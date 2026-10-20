<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use App\Modules\Paiements\Domain\Exceptions\ProviderNotResolvableException;
use App\Modules\Paiements\Domain\Models\PaymentFeeRule;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Infrastructure\Services\FeeRuleResolver;
use App\Modules\Paiements\Infrastructure\Services\ProviderResolver;
use App\Modules\Paiements\Infrastructure\Services\ProviderTypeRegistry;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8683, #8686, #8687) — registre, resolver, commissions.
 *
 * Le test central de ce fichier est `test_market_switch_without_code_change` :
 * c'est le critere d'acceptation du BC. Un admin desactive l'instance d'un
 * marche et en active une autre du meme type — et le marche bascule, sans
 * redeploiement ni modification de code.
 */
class PaymentProviderResolverTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private ProviderResolver $resolver;

    private FeeRuleResolver $fees;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->resolver = app(ProviderResolver::class);
        $this->fees = app(FeeRuleResolver::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeInstance(array $attributes = []): PaymentProviderInstance
    {
        /** @var PaymentProviderInstance $instance */
        $instance = PaymentProviderInstance::query()->create(array_merge([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Instance '.uniqid(),
            'countries' => ['DZ'],
            'currencies' => ['DZD'],
            'status' => 'active',
            'config' => ['success_rate' => 100],
            'priority' => 100,
        ], $attributes));

        return $instance;
    }

    public function test_registry_exposes_types_from_code_and_rejects_unknown(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        $codes = array_column($registry->all(), 'code');

        $this->assertContains(SimulatorRail::CODE, $codes);
        $this->assertTrue($registry->has(SimulatorRail::CODE));
        $this->assertInstanceOf(SimulatorRail::class, $registry->make(SimulatorRail::CODE));

        $this->expectException(\InvalidArgumentException::class);
        $registry->make('type-inexistant');
    }

    public function test_inactive_instance_is_never_resolved(): void
    {
        $this->makeInstance(['display_name' => 'Inactive', 'status' => 'inactive', 'priority' => 1]);

        $this->expectException(ProviderNotResolvableException::class);

        $this->resolver->resolve('DZ', 'DZD', (string) $this->company->id);
    }

    public function test_tenant_dedicated_instance_wins_over_global_at_equal_priority(): void
    {
        $global = $this->makeInstance(['display_name' => 'Global', 'priority' => 50]);
        $dedicated = $this->makeInstance([
            'display_name' => 'Dedie',
            'company_id' => $this->company->id,
            'priority' => 50,
        ]);

        $resolved = $this->resolver->resolve('DZ', 'DZD', (string) $this->company->id);

        $this->assertSame($dedicated->id, $resolved->id);
        $this->assertNotSame($global->id, $resolved->id);
    }

    public function test_priority_orders_candidates_and_market_coverage_filters(): void
    {
        $this->makeInstance(['display_name' => 'Autre pays', 'countries' => ['FR'], 'currencies' => ['EUR'], 'priority' => 1]);
        $low = $this->makeInstance(['display_name' => 'Prioritaire', 'priority' => 5]);
        $this->makeInstance(['display_name' => 'Secondaire', 'priority' => 90]);

        $resolved = $this->resolver->resolve('DZ', 'DZD', (string) $this->company->id);

        $this->assertSame($low->id, $resolved->id);
    }

    public function test_no_matching_instance_raises_explicit_business_error(): void
    {
        $this->makeInstance(['countries' => ['FR'], 'currencies' => ['EUR']]);

        $this->expectException(ProviderNotResolvableException::class);

        $this->resolver->resolve('DZ', 'DZD', (string) $this->company->id);
    }

    /**
     * CRITERE D'ACCEPTATION DU BC (#8693).
     *
     * Un admin desactive l'instance utilisee par un marche et en active une
     * autre du MEME TYPE. Le marche bascule. Aucune ligne de code n'a change :
     * seules deux lignes de configuration ont ete modifiees.
     */
    public function test_market_switch_without_code_change(): void
    {
        $instanceA = $this->makeInstance(['display_name' => 'Instance A', 'priority' => 10]);
        $instanceB = $this->makeInstance(['display_name' => 'Instance B', 'status' => 'inactive', 'priority' => 20]);

        // Etat initial : le marche DZ/DZD est servi par A.
        $this->assertSame($instanceA->id, $this->resolver->resolve('DZ', 'DZD', (string) $this->company->id)->id);

        // Bascule : A desactivee, B activee — meme type, aucune modification
        // de code, aucun deploiement.
        $instanceA->status = ProviderInstanceStatus::Inactive;
        $instanceA->save();

        $instanceB->status = ProviderInstanceStatus::Active;
        $instanceB->save();

        $resolved = $this->resolver->resolve('DZ', 'DZD', (string) $this->company->id);

        $this->assertSame($instanceB->id, $resolved->id);
        $this->assertSame($instanceA->type_code, $resolved->type_code);
    }

    public function test_fee_rule_specificity_cascade_and_priority(): void
    {
        $instance = $this->makeInstance();

        // Globale : 1 % (100 points de base).
        PaymentFeeRule::query()->create([
            'company_id' => null, 'operation_type' => 'cash_in', 'provider_instance_id' => null,
            'mode' => 'percentage', 'value' => 100, 'priority' => 100,
        ]);

        // Instance : 2 %.
        PaymentFeeRule::query()->create([
            'company_id' => null, 'operation_type' => 'cash_in', 'provider_instance_id' => $instance->id,
            'mode' => 'percentage', 'value' => 200, 'priority' => 100,
        ]);

        // Tenant : 3 %.
        PaymentFeeRule::query()->create([
            'company_id' => $this->company->id, 'operation_type' => 'cash_in', 'provider_instance_id' => null,
            'mode' => 'percentage', 'value' => 300, 'priority' => 100,
        ]);

        // Tenant + instance : 4 % — la plus specifique, elle doit gagner.
        PaymentFeeRule::query()->create([
            'company_id' => $this->company->id, 'operation_type' => 'cash_in', 'provider_instance_id' => $instance->id,
            'mode' => 'percentage', 'value' => 400, 'priority' => 100,
        ]);

        $this->assertSame(400, $this->fees->feeFor(10_000, 'cash_in', (string) $this->company->id, $instance->id));

        // Sans le niveau le plus specifique, c'est la regle tenant qui gagne.
        PaymentFeeRule::query()
            ->where('company_id', $this->company->id)
            ->where('provider_instance_id', $instance->id)
            ->delete();

        $this->assertSame(300, $this->fees->feeFor(10_000, 'cash_in', (string) $this->company->id, $instance->id));
    }

    public function test_fee_rule_min_max_bounds_and_zero_when_no_rule(): void
    {
        // Aucune regle : commission zero, jamais d'exception.
        $this->assertSame(0, $this->fees->feeFor(10_000, 'cash_out', (string) $this->company->id, null));

        PaymentFeeRule::query()->create([
            'company_id' => null, 'operation_type' => 'cash_out', 'provider_instance_id' => null,
            'mode' => 'fixed', 'value' => 50, 'min_amount' => 200, 'max_amount' => 1_000, 'priority' => 100,
        ]);

        // Borne basse appliquee.
        $this->assertSame(200, $this->fees->feeFor(10_000, 'cash_out', (string) $this->company->id, null));

        PaymentFeeRule::query()->where('operation_type', 'cash_out')->update(['value' => 5_000]);

        // Borne haute appliquee.
        $this->assertSame(1_000, $this->fees->feeFor(10_000, 'cash_out', (string) $this->company->id, null));
    }
}
