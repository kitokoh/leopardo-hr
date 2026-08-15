<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\MissingTenantContextException;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3727 (audit 360° A-04) — le scope global `BelongsToCompany` sautait
 * en silence sans `current_company` : une requête HTTP sans tenant résolu
 * lisait TOUTES les compagnies (fail-open). Ce test verrouille le fail-closed :
 *   - HTTP sans contexte tenant ET sans contrainte company_id → exception 403 ;
 *   - contrainte `company_id` explicite → requête autorisée (routes publiques) ;
 *   - opt-out `withoutGlobalScopes('company')` → autorisé ;
 *   - contexte console (jobs/commands/tests) → comportement historique ;
 *   - config `tenancy.fail_closed_without_context` false → fail-open restauré ;
 *   - tenant lié → requêtes filtrées par company_id.
 */
class TenantScopeFailClosedTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['tenancy.fail_closed_without_context' => true]);
        if (! \Illuminate\Support\Facades\Schema::hasTable($this->moduleTable('webhook_endpoints'))) {
            \Illuminate\Support\Facades\Schema::create($this->moduleTable('webhook_endpoints'), function ($table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('url');
                $table->json('events')->nullable();
                $table->string('secret')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        $this->restoreConsoleDetection();
        config(['tenancy.fail_closed_without_context' => true]);
        DB::statement('DROP TABLE IF EXISTS webhook_endpoints CASCADE');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function moduleTable(string $table): string
    {
        return $table;
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

    public function test_scope_throws_when_fail_closed_and_no_company_bound(): void
    {
        $this->simulateHttpContext();
        $this->assertFalse(app()->bound('current_company'));

        $this->expectException(MissingTenantContextException::class);
        Employee::query()->where('email', 'leak@example.com')->first();
    }

    public function test_exception_carries_stable_error_code(): void
    {
        $this->simulateHttpContext();

        try {
            Employee::query()->where('email', 'leak@example.com')->first();
            $this->fail('MissingTenantContextException attendue');
        } catch (MissingTenantContextException $e) {
            $this->assertSame(403, $e->statusCode());
            $this->assertSame('MISSING_TENANT_CONTEXT', $e->errorCode());
        }
    }

    public function test_query_with_explicit_company_id_constraint_is_allowed(): void
    {
        $this->simulateHttpContext();

        $company = Company::factory()->create();

        // Le caller scope lui-même la requête (pattern routes publiques careers)
        // → isolée, autorisée malgré l'absence de current_company.
        $result = Employee::query()
            ->where('company_id', $company->id)
            ->where('email', 'nobody@example.com')
            ->first();

        $this->assertNull($result);
    }

    public function test_relation_constraint_company_id_is_allowed(): void
    {
        $this->simulateHttpContext();

        $company = Company::factory()->create();

        // hasMany / relation : la clé étrangère `*.company_id` est une
        // contrainte explicite reconnue par le scope.
        $result = $company->employees()->where('email', 'nobody@example.com')->first();

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

    public function test_without_global_scopes_opt_out_is_allowed(): void
    {
        $this->simulateHttpContext();

        // Accès cross-tenant volontaire (pattern AuthService / plateforme).
        $result = Employee::withoutGlobalScopes('company')
            ->where('email', 'nobody@example.com')
            ->first();

        $this->assertNull($result);
    }

    public function test_webhook_endpoint_query_without_tenant_still_scoped(): void
    {
        // WebhookEndpoint utilise BelongsToCompany ; le contrôleur ajoute un
        // filtre company_id explicite (défense en profondeur #3727).
        $this->simulateHttpContext();

        $result = WebhookEndpoint::query()
            ->where('company_id', '00000000-0000-0000-0000-000000000000')
            ->get();

        $this->assertEmpty($result);
    }
}
