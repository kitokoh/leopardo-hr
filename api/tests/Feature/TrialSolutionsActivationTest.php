<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Jobs\ProvisionDemoTenantJob;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-25 (#6693) — activation des solutions sectorielles demandées à
 * l'inscription (SolutionActivator / feature flags) :
 *  - self_service : `solutions` au signup → activées au verify (OTP) ;
 *  - guided_trial : `solutions` transmises au job de provisioning ;
 *  - fail-closed : code inconnu → 422 INVALID_SOLUTION, jamais de tenant
 *    partiel ni d'activation silencieuse.
 *
 * Le manifest `fuel_station` (existant sur main) sert de sujet de test : le
 * mécanisme est générique, `restaurant` (PR #6663) fonctionnera de la même
 * façon dès son enregistrement au catalogue.
 */
class TrialSolutionsActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    public function test_signup_with_solution_activates_solution_and_required_modules(): void
    {
        Mail::fake();

        // Step 1 : signup avec la solution sectorielle demandée.
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'resto@newtech.dz',
            'company' => 'NewTech Restaurant',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
            'solutions' => ['fuel_station'],
        ])->assertStatus(200);

        $companyRequest = CompanyRequest::where('email', 'resto@newtech.dz')->first();
        $this->assertNotNull($companyRequest);
        $this->assertSame(['fuel_station'], $companyRequest->signup_payload['solutions'] ?? null);

        // Step 2 : verify OTP → provisioning + activation de la solution.
        $response = $this->postJson('/api/v1/trial/verify', [
            'email' => 'resto@newtech.dz',
            'code' => $companyRequest->verification_token,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $company = Company::find($response->json('data.company.id'));
        $this->assertNotNull($company);

        // La solution ET ses modules requis (pack) sont actifs.
        $this->assertTrue($company->hasFeature('fuel_station'));
        $this->assertTrue($company->hasFeature('attendance'));
        $this->assertTrue($company->hasFeature('documents'));
        $this->assertTrue($company->hasFeature('notifications'));
        $this->assertTrue($company->hasFeature('rh'));

        // Activation auditée (audit_logs = table tenant → contexte tenant,
        // pattern SelfServiceTrialTest).
        app(\App\Core\Tenant\TenantManager::class)->setTenant($company);
        try {
            $this->assertDatabaseHas('audit_logs', [
                'company_id' => $company->id,
                'action' => 'solution.activated',
            ]);
            $this->assertDatabaseHas('audit_logs', [
                'company_id' => $company->id,
                'action' => 'solution.dependencies_activated',
            ]);
        } finally {
            app(\App\Core\Tenant\TenantManager::class)->resetToPrevious();
        }
    }

    public function test_signup_rejects_unknown_solution_fail_closed(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'bad@newtech.dz',
            'company' => 'Bad Solution Co',
            'role' => 'founder',
            'employees' => '1-10',
            'country' => 'DZ',
            'solutions' => ['unknown_solution_xyz'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['solutions.0']);

        // Aucune demande créée (fail-closed avant écriture).
        $this->assertDatabaseMissing('company_requests', ['email' => 'bad@newtech.dz']);
    }

    public function test_guided_trial_dispatches_job_with_solutions_and_activates_them(): void
    {
        Mail::fake();
        Queue::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'guided@newtech.dz',
            'company' => 'Guided Restaurant',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
            'requestedWorkflow' => 'guided_trial',
            'solutions' => ['fuel_station'],
        ])->assertStatus(200);

        Queue::assertPushed(ProvisionDemoTenantJob::class, function (ProvisionDemoTenantJob $job): bool {
            return $job->solutions === ['fuel_station']
                && $job->email === 'guided@newtech.dz';
        });

        // Exécution directe du provisioning (sans passer par le handle du job,
        // qui contient issueDemoAccess — comportement job préexistant) : la
        // solution demandée doit être activée sur le tenant créé.
        $result = app(ProvisionGuidedTrial::class)->execute('guided@newtech.dz', 'Guided Restaurant', 'DZ', ['fuel_station']);

        $company = $result['company'];
        $this->assertNotNull($company);
        $this->assertTrue($company->hasFeature('fuel_station'));
        $this->assertTrue($company->hasFeature('attendance'));
        app(\App\Core\Tenant\TenantManager::class)->setTenant($company);
        try {
            $this->assertDatabaseHas('audit_logs', [
                'company_id' => $company->id,
                'action' => 'solution.activated',
            ]);
        } finally {
            app(\App\Core\Tenant\TenantManager::class)->resetToPrevious();
        }
    }

    public function test_activation_is_idempotent(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'idem@newtech.dz',
            'company' => 'Idem Restaurant',
            'role' => 'founder',
            'employees' => '1-10',
            'country' => 'DZ',
            'solutions' => ['fuel_station'],
        ])->assertStatus(200);

        $companyRequest = CompanyRequest::where('email', 'idem@newtech.dz')->first();
        $this->postJson('/api/v1/trial/verify', [
            'email' => 'idem@newtech.dz',
            'code' => $companyRequest->verification_token,
        ])->assertStatus(201);

        $company = Company::where('email', 'idem@newtech.dz')->first();
        $this->assertNotNull($company);

        // Ré-activer la solution déjà active : no-op, pas de doublon d'audit
        // (audit_logs = table tenant → contexte tenant).
        app(\App\Core\Tenant\TenantManager::class)->setTenant($company);
        try {
            $before = AuditLog::where('company_id', $company->id)
                ->where('action', 'solution.activated')
                ->count();

            $company->refresh();
            app(\App\Core\Solutions\SolutionActivator::class)->activateWithDependencies($company, 'fuel_station');

            $after = AuditLog::where('company_id', $company->id)
                ->where('action', 'solution.activated')
                ->count();
            $this->assertSame($before, $after);
        } finally {
            app(\App\Core\Tenant\TenantManager::class)->resetToPrevious();
        }
    }
}
