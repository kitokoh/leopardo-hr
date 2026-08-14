<?php

declare(strict_types=1);

namespace Tests\Feature\MultiCountry;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Jobs\ProvisionDemoTenantJob;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use App\Modules\Billing\Application\Actions\VerifyTrialSignup;
use App\Support\CountryDefaults;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MULTI-PAYS (#1950) — le pays validé du signup/provisioning n'est jamais
 * jeté ni remplacé par un fallback silencieux DZ : transmission au job,
 * résolution stricte (422 explicite) dans les 3 normalisations fautives.
 */
class ProvisioningCountryStrictTest extends TestCase
{
    use RefreshTenantDatabase;

    // ── Guided trial : le pays est transmis au job ────────────────────────

    public function test_guided_trial_signup_passes_country_to_provisioning_job(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'prospect-ci-'.uniqid().'@example.com',
            'company' => 'Prospect Abidjan',
            'role' => 'founder',
            'requestedWorkflow' => 'guided_trial',
            'country' => 'CI',
        ]);

        $response->assertOk();

        Bus::assertDispatched(ProvisionDemoTenantJob::class, function (ProvisionDemoTenantJob $job): bool {
            return $job->country === 'CI' && $job->email !== '' && $job->companyName !== '';
        });
    }

    // ── ProvisionGuidedTrial : tenant créé dans le pays demandé ───────────

    public function test_provision_guided_trial_creates_tenant_in_requested_country(): void
    {
        $provisioner = app(ProvisionGuidedTrial::class);

        $result = $provisioner->execute(
            'prospect-sn-'.uniqid().'@example.com',
            'Prospect Dakar SARL',
            'SN',
        );

        $this->assertTrue($result['success']);
        /** @var Company $company */
        $company = $result['company'];
        $this->assertSame('SN', $company->country);
        $this->assertSame('XOF', $company->currency);
        $this->assertSame('Africa/Dakar', $company->timezone);
        $this->assertSame('fr', $company->language);
    }

    public function test_provision_guided_trial_rejects_unknown_country(): void
    {
        $provisioner = app(ProvisionGuidedTrial::class);

        $this->expectException(\InvalidArgumentException::class);
        $provisioner->execute('bad@example.com', 'Bad Pays', 'ZZ');
    }

    // ── VerifyTrialSignup : payload legacy sans pays → 422 explicite ──────

    public function test_verify_rejects_signup_payload_without_country(): void
    {
        Mail::fake();

        // Signup self-service avec pays valide (créé la CompanyRequest).
        $email = 'legacy-'.uniqid().'@example.com';
        $this->postJson('/api/v1/trial/signup', [
            'email' => $email,
            'company' => 'Legacy SARL',
            'first_name' => 'Ali',
            'last_name' => 'Ben',
            'country' => 'DZ',
            'requestedWorkflow' => 'self_service',
        ])->assertOk();

        // Corrompre le payload pour simuler une demande legacy sans pays.
        /** @var CompanyRequest $request */
        $request = CompanyRequest::query()->where('email', $email)->firstOrFail();
        $payload = $request->signup_payload;
        unset($payload['country']);
        $request->update(['signup_payload' => $payload]);

        $verify = app(VerifyTrialSignup::class);
        $result = $verify->execute($email, (string) $request->verification_token);

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_COUNTRY', $result['error']);
        $this->assertSame(422, $result['status']);
    }

    // ── Registre : find() strict vs for() fallback affichage ──────────────

    public function test_country_defaults_find_is_strict_no_dz_fallback(): void
    {
        $this->assertNull(CountryDefaults::find('ZZ'));
        $this->assertNull(CountryDefaults::find(''));
        $this->assertNull(CountryDefaults::find(null));
        $this->assertSame('CI', CountryDefaults::find('ci')['country']);

        // for() reste réservé à l'affichage (fallback DZ documenté).
        $this->assertSame('DZ', CountryDefaults::for('ZZ')['country']);
    }

    private function superAdmin(): SuperAdmin
    {
        return new SuperAdmin([
            'id' => 1,
            'name' => 'Audit',
            'email' => 'audit@leopardo.test',
        ]);
    }
}
