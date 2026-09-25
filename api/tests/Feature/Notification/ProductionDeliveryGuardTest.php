<?php

namespace Tests\Feature\Notification;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Notification\Infrastructure\Services\ProductionDeliveryGuard;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionDeliveryGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore the test environment no matter what a test mutated.
        config(['app.env' => 'testing']);
        config(['mail.default' => 'log']);
        config(['communication.providers.whatsapp' => 'audit']);
        parent::tearDown();
    }

    public function test_guard_is_not_applicable_outside_production(): void
    {
        config(['app.env' => 'testing']);

        $guard = new ProductionDeliveryGuard;

        $this->assertFalse($guard->isApplicable());
        $this->assertSame([], $guard->issues());
    }

    public function test_flags_log_mailer_in_production(): void
    {
        $this->simulateProduction(['mail.default' => 'log']);

        $this->assertSame(
            [ProductionDeliveryGuard::ISSUE_MAILER_LOG],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    public function test_flags_array_mailer_in_production(): void
    {
        $this->simulateProduction(['mail.default' => 'array']);

        $this->assertSame(
            [ProductionDeliveryGuard::ISSUE_MAILER_LOG],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    public function test_flags_sandbox_smtp_host_in_production(): void
    {
        $this->simulateProduction([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io',
        ]);

        $this->assertSame(
            [ProductionDeliveryGuard::ISSUE_SMTP_SANDBOX_HOST],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    public function test_accepts_real_smtp_host_in_production(): void
    {
        $this->simulateProduction([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
        ]);

        $this->assertSame([], (new ProductionDeliveryGuard)->issues());
    }

    public function test_flags_sandbox_mailgun_domain_in_production(): void
    {
        $this->simulateProduction([
            'mail.default' => 'mailgun',
            'mail.mailers.mailgun.domain' => 'sandbox8f0f0abc1d2e3f4a5b6c7d8e9f0a1b2c.mailgun.org',
        ]);

        $this->assertSame(
            [ProductionDeliveryGuard::ISSUE_MAILGUN_SANDBOX_DOMAIN],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    public function test_accepts_verified_mailgun_domain_in_production(): void
    {
        $this->simulateProduction([
            'mail.default' => 'mailgun',
            'mail.mailers.mailgun.domain' => 'mail.leopardo-rh.com',
        ]);

        $this->assertSame([], (new ProductionDeliveryGuard)->issues());
    }

    public function test_flags_whatsapp_provider_without_secrets_in_production(): void
    {
        $this->simulateProduction([
            'communication.providers.whatsapp' => 'whatsapp_cloud',
            'services.whatsapp.phone_number_id' => '',
            'services.whatsapp.access_token' => '',
        ]);

        $this->assertSame(
            [ProductionDeliveryGuard::ISSUE_WHATSAPP_MISSING_SECRETS],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    public function test_flags_whatsapp_provider_with_partial_secrets_in_production(): void
    {
        $this->simulateProduction([
            'communication.providers.whatsapp' => 'whatsapp_cloud',
            'services.whatsapp.phone_number_id' => '123456789012345',
            'services.whatsapp.access_token' => '',
        ]);

        $this->assertSame(
            [ProductionDeliveryGuard::ISSUE_WHATSAPP_MISSING_SECRETS],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    public function test_accepts_whatsapp_provider_with_secrets_in_production(): void
    {
        $this->simulateProduction([
            'communication.providers.whatsapp' => 'whatsapp_cloud',
            'services.whatsapp.phone_number_id' => '123456789012345',
            'services.whatsapp.access_token' => 'EAA-test-token',
        ]);

        $this->assertSame([], (new ProductionDeliveryGuard)->issues());
    }

    public function test_accepts_audit_whatsapp_in_production(): void
    {
        // 'audit' is the explicit default: no real provider requested, the
        // guard stays silent (the audit-only fallback is expected).
        $this->simulateProduction(['communication.providers.whatsapp' => 'audit']);

        $this->assertSame([], (new ProductionDeliveryGuard)->issues());
    }

    public function test_accumulates_multiple_issues_in_production(): void
    {
        $this->simulateProduction([
            'mail.default' => 'log',
            'communication.providers.whatsapp' => 'whatsapp_cloud',
            'services.whatsapp.phone_number_id' => '',
            'services.whatsapp.access_token' => '',
        ]);

        $this->assertSame(
            [
                ProductionDeliveryGuard::ISSUE_MAILER_LOG,
                ProductionDeliveryGuard::ISSUE_WHATSAPP_MISSING_SECRETS,
            ],
            (new ProductionDeliveryGuard)->issues()
        );
    }

    /**
     * #8125 — la matrice détaillée de /health (drivers, mémoire, latences)
     * est réservée aux super admins plateforme ; la surface publique reste
     * minimale pour les sondes ({"status":"ok"}).
     */
    private function actingAsSuperAdmin(): void
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-health@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    public function test_health_public_response_is_minimal(): void
    {
        // #8125 — sans authentification, /health n'expose ni la matrice
        // d'infrastructure ni l'environnement (cartographie de stack).
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonMissingPath('checks');
        $response->assertJsonMissingPath('environment');
    }

    public function test_health_exposes_delivery_check(): void
    {
        // Outside production the check is skipped (non-blocking, additive).
        // #8125 : la matrice détaillée exige un super admin authentifié.
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonPath('checks.delivery.status', 'skipped');
        $response->assertJsonPath('checks.delivery.ok', true);
    }

    public function test_health_delivery_check_is_degraded_in_production_with_log_mailer(): void
    {
        config(['app.env' => 'production']);
        config(['mail.default' => 'log']);

        try {
            $this->actingAsSuperAdmin();

            $response = $this->getJson('/api/v1/health');

            // HTTP stays 200: the 503 is driven by the database check only.
            $response->assertOk();
            $response->assertJsonPath('checks.delivery.status', 'degraded');
            $response->assertJsonPath('checks.delivery.ok', false);
            $response->assertJsonPath(
                'checks.delivery.issues',
                [ProductionDeliveryGuard::ISSUE_MAILER_LOG]
            );
        } finally {
            config(['app.env' => 'testing']);
            config(['mail.default' => 'log']);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function simulateProduction(array $config): void
    {
        config(['app.env' => 'production']);

        foreach ($config as $key => $value) {
            config([$key => $value]);
        }
    }
}
