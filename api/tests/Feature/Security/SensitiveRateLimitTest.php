<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SensitiveRateLimitTest extends TestCase
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

    public function test_public_auth_login_is_rate_limited_by_email_and_ip(): void
    {
        config(['security.rate_limits.auth_per_minute' => 2]);

        $payload = [
            'email' => 'limited@example.test',
            'password' => 'wrong-password',
            'device_name' => 'test-suite',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    public function test_privacy_export_is_rate_limited_per_authenticated_employee(): void
    {
        config(['security.rate_limits.privacy_per_minute' => 2]);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'privacy-rate-limit@example.test',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/privacy/export')->assertOk();
        $this->getJson('/api/v1/privacy/export')->assertOk();
        $this->getJson('/api/v1/privacy/export')->assertStatus(429);
    }

    public function test_inbound_webhooks_are_rate_limited_per_ip(): void
    {
        config(['security.rate_limits.webhooks_inbound_per_minute' => 2]);

        $payload = [
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_rate_limit_test']],
        ];

        $this->postJson('/api/v1/webhooks/stripe', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/stripe', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/stripe', $payload)->assertStatus(429);
    }

    public function test_web_login_is_rate_limited_by_email_and_ip(): void
    {
        config(['security.rate_limits.web_login_per_minute' => 2]);

        $payload = [
            'email' => 'web-login-limited@example.test',
            'password' => 'wrong-password',
        ];

        $this->get('/login');
        $token = session()->token();

        $this->withSession(['_token' => $token])->post('/login', ['_token' => $token, ...$payload])->assertRedirect('/login');
        $this->withSession(['_token' => $token])->post('/login', ['_token' => $token, ...$payload])->assertRedirect('/login');
        $this->withSession(['_token' => $token])->post('/login', ['_token' => $token, ...$payload])->assertStatus(429);
    }

    public function test_platform_login_is_rate_limited_by_email_and_ip(): void
    {
        config(['security.rate_limits.web_login_per_minute' => 2]);

        $payload = [
            'email' => 'platform-login-limited@example.test',
            'password' => 'wrong-password',
        ];

        $this->get('/platform/login');
        $token = session()->token();

        $this->withSession(['_token' => $token])->post('/platform/login', ['_token' => $token, ...$payload])->assertRedirect('/platform/login');
        $this->withSession(['_token' => $token])->post('/platform/login', ['_token' => $token, ...$payload])->assertRedirect('/platform/login');
        $this->withSession(['_token' => $token])->post('/platform/login', ['_token' => $token, ...$payload])->assertStatus(429);
    }

    public function test_kiosk_web_punch_is_rate_limited_by_device_code(): void
    {
        config(['security.rate_limits.kiosk_punch_per_minute' => 2]);

        $company = Company::factory()->create();
        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Rate Limit Kiosk',
            'device_code' => 'RATELIMIT01',
            'biometric_mode' => 'fingerprint',
            'sync_token_hash' => Hash::make('plain-token'),
            'status' => 'active',
        ]);

        $payload = ['identifier' => 'unknown-employee', 'action' => 'check_in'];

        $this->post('/kiosk/'.$kiosk->device_code.'/punch', $payload);
        $this->post('/kiosk/'.$kiosk->device_code.'/punch', $payload);
        $this->post('/kiosk/'.$kiosk->device_code.'/punch', $payload)->assertStatus(429);
    }
}
