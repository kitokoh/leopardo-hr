<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Foundation\Application;
use ReflectionProperty;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3727 (T004) : le scope global BelongsToCompany était fail-open —
 * sans `current_company` liée, une requête balayait toutes les compagnies
 * (schéma partagé) : fuite cross-tenant silencieuse.
 *
 * Correctif : en contexte HTTP, une requête sans contexte tenant ET sans
 * contrainte `company_id` explicite échoue avec
 * `TenantContextMissingException` (fail-closed). Les requêtes scopées
 * explicitement, les relations et le contexte console conservent leur
 * comportement.
 */
class TenantScopeFailClosedTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['tenancy.fail_closed_without_context' => true]);
        config(['tenancy.log_missing_tenant_context' => false]);
    }

    protected function tearDown(): void
    {
        $this->restoreConsoleDetection();
        config(['tenancy.fail_closed_without_context' => true]);
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * Simule un contexte HTTP : `runningInConsole()` renvoie false alors que
     * phpunit tourne en CLI (SAPI). Le flag est mis en cache par l'Application
     * — on le force par réflexion et on le restaure après le test.
     */
    private function simulateHttpContext(): void
    {
        $property = new ReflectionProperty(Application::class, 'isRunningInConsole');
        $property->setAccessible(true);
        $property->setValue(app(), false);
    }

    private function restoreConsoleDetection(): void
    {
        $property = new ReflectionProperty(Application::class, 'isRunningInConsole');
        $property->setAccessible(true);
        $property->setValue(app(), null);
    }

    public function test_query_without_tenant_context_fails_closed_in_http(): void
    {
        $this->simulateHttpContext();

        $this->expectException(TenantContextMissingException::class);
        $this->expectExceptionMessage(Employee::class);

        // Requête sans `current_company` liée et sans contrainte company_id :
        // c'est le scénario de fuite cross-tenant — doit échouer explicitement.
        Employee::query()->where('email', 'leak@example.com')->first();
    }

    public function test_query_with_explicit_company_id_constraint_is_allowed(): void
    {
        $this->simulateHttpContext();

        $company = Company::factory()->create();

        // Le caller scope lui-même la requête → isolée, autorisée.
        $result = Employee::query()
            ->where('company_id', $company->id)
            ->where('email', 'nobody@example.com')
            ->first();

        $this->assertNull($result);
    }

    public function test_console_context_keeps_legacy_behavior(): void
    {
        // Contexte console (défaut phpunit) : comportement historique conservé.
        $result = Employee::query()->where('email', 'nobody@example.com')->first();

        $this->assertNull($result);
    }

    public function test_config_disabled_keeps_fail_open(): void
    {
        $this->simulateHttpContext();
        config(['tenancy.fail_closed_without_context' => false]);

        $result = Employee::query()->where('email', 'nobody@example.com')->first();

        $this->assertNull($result);
    }
}
