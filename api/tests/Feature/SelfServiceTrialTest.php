<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\TenantManager;
use App\Mail\TrialVerificationMail;
use App\Mail\TrialWelcomeMail;
use App\Modules\Billing\Application\Actions\RequestTrialSignup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SelfServiceTrialTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_signup_sends_otp_and_creates_pending_request()
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@newtech.dz',
            'company' => 'NewTech Algeria',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Code de vérification envoyé.',
                'data' => [
                    'email' => 'founder@newtech.dz',
                    'status' => 'pending_verification',
                ],
            ]);

        // Verify CRM record created with pending status
        $this->assertDatabaseHas('company_requests', [
            'email' => 'founder@newtech.dz',
            'company_name' => 'NewTech Algeria',
            'status' => 'pending',
        ]);

        // Verify OTP email sent
        Mail::assertSent(TrialVerificationMail::class, function ($mail) {
            return $mail->hasTo('founder@newtech.dz');
        });
    }

    public function test_can_verify_otp_and_provision_trial_tenant()
    {
        Mail::fake();

        // Step 1: signup to get OTP
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@newtech.dz',
            'company' => 'NewTech Algeria',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ])->assertStatus(200);

        // Get the OTP from the database
        $companyRequest = CompanyRequest::where('email', 'founder@newtech.dz')
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($companyRequest);
        $otp = $companyRequest->verification_token;

        // Step 2: verify
        $response = $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@newtech.dz',
            'code' => $otp,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Votre espace Leopardo est prêt !',
            ])
            ->assertJsonStructure([
                'data' => [
                    'company' => ['id', 'name', 'slug'],
                    'manager' => ['email', 'first_name', 'last_name'],
                    'trial' => ['days', 'ends_at'],
                ],
            ]);

        // Cohérence essai (constat QA live 2026-08-15, #3012/#3056) : la
        // réponse doit annoncer le même nombre de jours que le
        // provisionnement réel (14 j — VerifyTrialSignup + plan fallback).
        $this->assertSame(14, $response->json('data.trial.days'));

        // #2680 — le mot de passe temporaire ne doit JAMAIS transiter dans la
        // réponse JSON (fuite potentielle via logs/proxy) : il part par email.
        $this->assertArrayNotHasKey('temp_password', $response->json('data.manager'));

        // Verify company created
        $companyId = $response->json('data.company.id');
        $company = Company::find($companyId);
        $this->assertNotNull($company);
        $this->assertEquals('NewTech Algeria', $company->name);
        $this->assertEquals('shared_tenants', $company->schema_name);
        $this->assertEquals('trial', $company->status);
        $this->assertEquals('fr', $company->language); // From DZ defaults

        // Verify manager created in the tenant
        app(TenantManager::class)->setTenant($company);

        $manager = Employee::where('email', 'founder@newtech.dz')->first();
        $this->assertNotNull($manager);
        $this->assertEquals('principal', $manager->manager_role);
        $this->assertEquals('Founder', $manager->first_name); // Auto-extracted
        $this->assertEquals('Newtech.dz', $manager->last_name);

        app(TenantManager::class)->resetToPrevious();

        // Verify CRM record updated to approved
        $this->assertDatabaseHas('company_requests', [
            'email' => 'founder@newtech.dz',
            'company_name' => 'NewTech Algeria',
            'status' => 'approved',
            'approved_company_id' => $company->id,
        ]);

        // Verify Welcome Mail sent after verification
        Mail::assertSent(TrialWelcomeMail::class, function ($mail) {
            return $mail->hasTo('founder@newtech.dz')
                && $mail->trialDays === 14;
        });
    }

    public function test_rejects_invalid_otp()
    {
        Mail::fake();

        // Step 1: signup
        // MULTI-PAYS (#1867) : le pays est désormais requis — payload valide.
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@invalid.com',
            'company' => 'Invalid Test',
            'country' => 'DZ',
        ])->assertStatus(200);

        // Step 2: wrong OTP
        $response = $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@invalid.com',
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'INVALID_OR_EXPIRED_CODE',
            ]);
    }

    public function test_signup_does_not_enumerate_existing_manager_email()
    {
        Mail::fake();

        // Full signup + verify flow for first account
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@existing.com',
            'company' => 'First Company',
            'country' => 'DZ',
        ])->assertStatus(200);

        $otp = CompanyRequest::where('email', 'founder@existing.com')
            ->where('status', 'pending')->first()->verification_token;

        $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@existing.com',
            'code' => $otp,
        ])->assertStatus(201);

        // Anti-énumération (#3945) : un second signup avec le même email
        // reçoit la MÊME réponse uniforme (200) que pour un email inconnu —
        // plus de 409 EMAIL_ALREADY_REGISTERED sur l'endpoint public.
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@existing.com',
            'company' => 'Second Company',
            'country' => 'DZ',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending_verification');
    }

    public function test_verify_rejects_duplicate_manager_email_after_otp()
    {
        Mail::fake();

        // Full signup + verify flow for first account
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@existing.com',
            'company' => 'First Company',
            'country' => 'DZ',
        ])->assertStatus(200);

        $otp = CompanyRequest::where('email', 'founder@existing.com')
            ->where('status', 'pending')->first()->verification_token;

        $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@existing.com',
            'code' => $otp,
        ])->assertStatus(201);

        // Second signup (réponse uniforme) → un OTP est émis.
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@existing.com',
            'company' => 'Second Company',
            'country' => 'DZ',
        ])->assertStatus(200);

        $secondOtp = CompanyRequest::where('email', 'founder@existing.com')
            ->where('status', 'pending')->first()->verification_token;

        // La détection du doublon vit à l'étape verify : possession de la
        // boîte mail prouvée (OTP valide) → 409 EMAIL_ALREADY_REGISTERED,
        // sans provisionner de second tenant.
        $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@existing.com',
            'code' => $secondOtp,
        ])->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => 'EMAIL_ALREADY_REGISTERED',
            ]);
    }

    public function test_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/trial/signup', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'company']);
    }

    public function test_second_verify_with_same_otp_does_not_double_provision()
    {
        // QA #2996 — race double-provisioning : deux verify avec le même OTP
        // valide ne doivent créer qu'UN SEUL tenant/manager.
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@race-test.dz',
            'company' => 'Race Test Algeria',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ])->assertStatus(200);

        $otp = CompanyRequest::where('email', 'founder@race-test.dz')
            ->where('status', 'pending')->first()->verification_token;

        // 1er verify → succès + provisioning
        $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@race-test.dz',
            'code' => $otp,
        ])->assertStatus(201);

        // 2e verify (simule la 2e requête concurrente) → refus, aucun second tenant
        $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@race-test.dz',
            'code' => $otp,
        ])->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'INVALID_OR_EXPIRED_CODE',
            ]);

        // Un seul tenant créé pour cet email
        $this->assertSame(
            1,
            DB::table('companies')
                ->where('name', 'Race Test Algeria')
                ->count(),
            'Le double verify ne doit pas créer deux tenants.'
        );
    }

    public function test_verify_returns_409_when_request_already_claimed()
    {
        // QA #2996 — une demande déjà claimée (statut processing, verrou
        // posé par une requête concurrente) → 409 ALREADY_PROCESSED, sans
        // aucun provisioning.
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@claimed.dz',
            'company' => 'Claimed Test',
            'country' => 'DZ',
        ])->assertStatus(200);

        $request = CompanyRequest::where('email', 'founder@claimed.dz')
            ->where('status', 'pending')->first();
        $otp = $request->verification_token;

        // Simule le claim d'une requête concurrente (fenêtre de provisioning)
        $request->update(['status' => 'processing']);

        $this->postJson('/api/v1/trial/verify', [
            'email' => 'founder@claimed.dz',
            'code' => $otp,
        ])->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => 'ALREADY_PROCESSED',
            ]);

        $this->assertSame(
            0,
            DB::table('companies')
                ->where('name', 'Claimed Test')
                ->count(),
            'Aucun tenant ne doit être créé pour une demande déjà claimée.'
        );
    }

    // Issue #3057 / #4949 / #5162 — l'échec d'envoi de l'OTP ne doit jamais
    // répondre « Code envoyé » : le lead est conservé (ligne pending) mais
    // l'état est honnête → 503 TRIAL_OTP_SEND_FAILED, jamais un 200.
    // (Contract aligné sur le changement #4874 : chemin legacy → 503 localisé.)
    public function test_signup_reports_honest_state_when_otp_email_fails(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('smtp unavailable'));

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder.otp.fail@newtech.dz',
            'company' => 'NewTech OTP Fail',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'TRIAL_OTP_SEND_FAILED');

        // La demande est bien conservée malgré l'échec du mail.
        $this->assertDatabaseHas('company_requests', [
            'email' => 'founder.otp.fail@newtech.dz',
            'status' => 'pending',
        ]);
    }

    public function test_legacy_path_db_failure_returns_503_not_500(): void
    {
        // Issue #4866 : le chemin legacy (sans requestedWorkflow) ne doit
        // JAMAIS répondre 500 INTERNAL_ERROR quand la création de la
        // CompanyRequest échoue (observé en prod) — 503 + message localisé.
        // On simule l'échec de l'action métier.
        // Pattern identique au test frère (andThrow mockery, lignes 331-333).
        $this->partialMock(RequestTrialSignup::class)
            ->shouldReceive('execute')
            ->andThrow(new \RuntimeException('db unavailable'));

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'legacy.fail@newtech.dz',
            'company' => 'Legacy Fail',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
            'requestedWorkflow' => 'self_service',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error', 'TRIAL_SIGNUP_UNAVAILABLE')
            ->assertJsonPath('success', false);
    }

    public function test_self_service_otp_send_failure_returns_503_trial_otp_send_failed(): void
    {
        // Issue #5162 / #4949 : le parcours self_service (OTP) échouait en
        // prod avec 503 TRIAL_OTP_SEND_FAILED (envoi impossible). Le contrat
        // honnête : si l'email OTP ne part pas (mailer KO), la réponse est
        // 503 TRIAL_OTP_SEND_FAILED — JAMAIS un 200 « code envoyé ».
        // On simule l'échec d'envoi de l'action métier (execute → false).
        $this->partialMock(RequestTrialSignup::class)
            ->shouldReceive('execute')
            ->andReturn(false);

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'otp.503@newtech.dz',
            'company' => 'OTP 503',
            'country' => 'DZ',
            'requestedWorkflow' => 'self_service',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error', 'TRIAL_OTP_SEND_FAILED')
            ->assertJsonPath('success', false);
    }
}
