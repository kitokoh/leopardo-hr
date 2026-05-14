<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
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
}
