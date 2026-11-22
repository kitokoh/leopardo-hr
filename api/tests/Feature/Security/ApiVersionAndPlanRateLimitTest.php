<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ApiVersionAndPlanRateLimitTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_api_responses_include_version_headers(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk();
        $response->assertHeader('X-API-Version', 'v1');
        $response->assertHeader('X-API-Supported-Versions', 'v1');
    }

    public function test_unsupported_requested_api_version_is_rejected(): void
    {
        $response = $this->withHeader('X-API-Version', 'v2')->getJson('/api/v1/health/live');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'UNSUPPORTED_API_VERSION',
                'requested_version' => 'v2',
                'supported_versions' => ['v1'],
            ]);
    }

    public function test_authenticated_api_is_rate_limited_by_current_plan(): void
    {
        // #9793 — le compteur de quota n'est pas une valeur absolue mais une
        // FENETRE REELLE de 60 s : `Limit::perMinute()` fixe `decaySeconds`,
        // `RateLimiter::hit()` pose la cle `:timer`, et
        // `RateLimiter::tooManyAttempts()` remet les tentatives a zero des que
        // cette cle a expire. Sans horloge gelee, un runner lent laisse la
        // fenetre s'ecouler entre la 1re et la 3e requete : le test echouait en
        // « 200 au lieu de 429 » sans qu'une ligne de produit ne change. Le
        // store de cache des tests est `array` (phpunit.xml), et
        // `ArrayStore` calcule ses expirations avec `Carbon::now()`, donc
        // l'horloge gelee rend la fenetre infranchissable.
        $this->freezeTime();

        // Issue #4683 / #4456 (ADR-0014) : codes plans canoniques
        // Free/Pilot/Operations/Enterprise — « Starter » est un legacy
        // migré vers Pilot par PlanSeeder, et resolvePlanLimit() ne lit
        // que pilot_per_minute (jamais starter_per_minute).
        config(['security.plan_rate_limits.pilot_per_minute' => 2]);

        DB::table('plans')->updateOrInsert(['name' => 'Pilot'], [
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_employees' => 20,
            'features' => '{}',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $planId = DB::table('plans')->where('name', 'Pilot')->value('id');

        $company = Company::factory()->create(['plan_id' => $planId]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'plan-rate-limit@example.test',
        ]);

        // #9793 — le quota est resolu depuis le plan de la societe. On
        // l'asserte explicitement : un repli silencieux (Free 60/min,
        // Enterprise illimite, ou employe non reconnu -> 100/min par IP)
        // produisait le meme « 200 au lieu de 429 » muet que l'expiration de la
        // fenetre, sans permettre de distinguer les causes.
        $limiter = RateLimiter::limiter('api-plan');
        if (! $limiter instanceof \Closure) {
            $this->fail('Le limiter nomme api-plan a disparu.');
        }

        $planRequest = Request::create('/api/v1/auth/me', 'GET');
        $planRequest->setUserResolver(static fn (): Employee => $employee);

        $resolved = $limiter($planRequest);
        if (! $resolved instanceof Limit) {
            $this->fail('api-plan ne resout plus une limite nommee.');
        }

        $this->assertSame(
            2,
            $resolved->maxAttempts,
            'Le quota n\'est pas celui du plan Pilot : la resolution du plan de la societe est cassee.',
        );
        $this->assertSame(
            'plan:pilot:company:'.$company->id,
            $resolved->key,
            'La cle du quota ne porte plus le plan et la societe attendus.',
        );

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertStatus(429);
    }
}
