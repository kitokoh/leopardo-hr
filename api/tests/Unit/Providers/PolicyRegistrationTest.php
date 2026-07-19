<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Billing\Domain\Models\FeaturePlanMatrix;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Policies\InvoicePolicy;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PA2-ARCH-008 : point d'enregistrement unique pour les Gate::policy(...).
 *
 * Avant ce ticket, `AppServiceProvider::boot()` et `AuthServiceProvider::boot()`
 * enregistraient chacun leur propre jeu de `Gate::policy(...)`, avec des
 * doublons (Attendance, Employee, Evaluation, Payroll, Recruitment, Training,
 * Vehicle, Subscription...) et une vraie divergence sur `Invoice`
 * (BillingPolicy dans AppServiceProvider vs InvoicePolicy dans
 * AuthServiceProvider). Ce test garantit :
 *  1. Qu'un seul des deux providers appelle encore `Gate::policy(...)`.
 *  2. Que le modele `Invoice` resout explicitement vers `InvoicePolicy`.
 */
class PolicyRegistrationTest extends TestCase
{
    public function test_only_one_service_provider_registers_gate_policies(): void
    {
        $appProviderCallsPolicy = $this->sourceContainsGatePolicyCall(AppServiceProvider::class);
        $authProviderCallsPolicy = $this->sourceContainsGatePolicyCall(AuthServiceProvider::class);

        $this->assertTrue(
            $appProviderCallsPolicy xor $authProviderCallsPolicy,
            'Exactement un seul provider (AppServiceProvider XOR AuthServiceProvider) doit '
            .'appeler Gate::policy(...). Sinon la dette PA2-ARCH-008 (doublons/divergences '
            .'d\'enregistrement de policies) est de retour.'
        );
    }

    public function test_invoice_policy_is_unambiguously_invoice_policy(): void
    {
        $this->assertSame(
            InvoicePolicy::class,
            get_class(Gate::getPolicyFor(Invoice::class)),
            'La divergence Invoice (BillingPolicy vs InvoicePolicy) doit rester tranchee '
            .'explicitement en faveur de InvoicePolicy (scoping company_id + roles dedies).'
        );
    }

    public function test_core_models_still_resolve_a_policy(): void
    {
        $this->assertNotNull(Gate::getPolicyFor(Employee::class));
        $this->assertNotNull(Gate::getPolicyFor(OnboardingStep::class));
        $this->assertNotNull(Gate::getPolicyFor(FeaturePlanMatrix::class));
    }

    private function sourceContainsGatePolicyCall(string $providerClass): bool
    {
        $reflection = new ReflectionClass($providerClass);
        $bootMethod = $reflection->getMethod('boot');

        $source = $this->extractMethodSource($bootMethod);

        // Only count real calls, e.g. "Gate::policy(Foo::class, FooPolicy::class);",
        // not documentation comments that merely mention the pattern.
        return (bool) preg_match('/Gate::policy\(\s*\S+::class\s*,/', $source);
    }

    private function extractMethodSource(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($filename);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
