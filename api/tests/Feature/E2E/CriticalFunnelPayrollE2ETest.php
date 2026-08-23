<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5285 — parcours critique 1, E2E en CI (module platform).
 *
 *   signup trial (guided) → provision → employés (HTTP) → run de paie
 *   (moteur RÉEL) → bulletin PDF téléchargeable.
 *
 * Différence avec les tests existants :
 *   - TrialSignupLocalizationTest / TrialSignupSlugRaceTest : endpoints trial
 *     isolés, pas de provision ni de paie ;
 *   - OnboardingE2ETest : onboarding super-admin → RH → employé, sans paie ;
 *   - PayrollRunClosingE2ETest : part d'une company factory (pas du funnel
 *     prospect) ;
 *   - ici, TOUT le funnel prospect est traversé via HTTP avec le code réel :
 *     POST /api/v1/trial/signup → ProvisionGuidedTrial (le code exécuté par
 *     ProvisionDemoTenantJob) → POST /api/v1/employees → POST
 *     /api/v1/payroll-runs → calculate → validate → GET
 *     /me/pay-slips/{id}/pdf.
 */
class CriticalFunnelPayrollE2ETest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_funnel_signup_to_payslip_pdf_via_real_engine(): void
    {
        // PDF générés sur des disques fake : aucun fichier réel en CI.
        Storage::fake('local');
        Storage::fake('private');

        $email = 'prospect-'.time().'@e2e.leopardo.test';
        $companyName = 'E2E Funnel SARL';
        $country = 'DZ';

        // ── 1. Signup trial RÉEL (guided_trial) ───────────────────────────────
        $signup = $this->postJson('/api/v1/trial/signup', [
            'email' => $email,
            'company' => $companyName,
            'country' => $country,
            'requestedWorkflow' => 'guided_trial',
        ]);
        $signup->assertOk()
            ->assertJsonPath('data.status', 'provisioning_sandbox');

        $provisioningToken = (string) $signup->json('data.provisioning_token');
        $this->assertNotEmpty($provisioningToken, 'un provisioning_token doit être retourné');

        $provisioning = DB::table('trial_provisionings')->where('email', $email)->first();
        $this->assertNotNull($provisioning, 'la ligne trial_provisionings doit exister');
        $this->assertSame('pending', $provisioning->status);

        // ── 2. Provision — le code réel du job (ProvisionDemoTenantJob) ───────
        $provisioned = app(ProvisionGuidedTrial::class)->execute($email, $companyName, $country);
        $this->assertTrue($provisioned['success']);

        /** @var Company $company */
        $company = Company::query()
            ->where('email', $email)
            ->where('status', 'trial')
            ->firstOrFail();

        DB::statement('SET search_path TO shared_tenants,public');

        /** @var Employee $manager */
        $manager = Employee::query()->where('email', $email)->firstOrFail();
        $this->assertSame('manager', $manager->role);
        $this->assertSame('principal', $manager->manager_role);

        DB::table('trial_provisionings')->where('email', $email)->update(['status' => 'ready']);

        // ── 3. Employés via HTTP (régression #4947 : password → 201) ──────────
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Yasmine',
            'last_name' => 'Benali',
            'email' => 'yasmine.benali@e2e.leopardo.test',
            'role' => 'employee',
            'password' => 'ProvidedPass123!',
            'gross_salary' => 60000,
            'country' => $country,
        ])->assertCreated();

        $employee = Employee::query()->where('email', 'yasmine.benali@e2e.leopardo.test')->firstOrFail();
        $this->assertSame(60000.0, (float) $employee->salary_base);

        // ── 4. Run de paie via HTTP, moteur RÉEL ──────────────────────────────
        // Grille salariale DZ active (pattern PayrollRunClosingE2ETest #5150).
        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille E2E funnel (5285)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => $country,
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $runResponse = $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $country,
            'notes' => 'Funnel critique E2E #5285',
        ]);
        $runResponse->assertCreated()
            ->assertJsonPath('data.status', PayrollRun::STATUS_DRAFT);
        $runId = (int) $runResponse->json('data.id');

        $calculated = $this->postJson("/api/v1/payroll-runs/{$runId}/calculate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_CALCULATED);
        $this->assertGreaterThanOrEqual(1, (int) $calculated->json('data.pay_slips_count'));

        $this->postJson("/api/v1/payroll-runs/{$runId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRun::STATUS_VALIDATED);

        // ── 5. Bulletin : PDF réel généré et téléchargeable ───────────────────
        /** @var PaySlip $slip */
        $slip = PaySlip::query()->where('payroll_run_id', $runId)->firstOrFail();
        $this->assertSame('validated', $slip->status);

        $pdf = $this->getJson("/api/v1/me/pay-slips/{$slip->id}/pdf");
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));

        // Bulletin réel (signature %PDF), pas un stub.
        $this->assertStringStartsWith('%PDF', substr((string) $pdf->getContent(), 0, 4));
    }
}
