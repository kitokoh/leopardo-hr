<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Services\SuperAdminService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    use CreatesMvpSchema;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->superAdmin = clone SuperAdmin::query()->create([
            'name' => 'Test Super Admin',
            'email' => 'admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_can_login_without_2fa_if_not_enabled(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token']);
    }

    public function test_super_admin_can_setup_2fa(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/setup');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['secret', 'qr_code_url']]);

        $secret = $response->json('data.secret');
        $this->assertNotEmpty($secret);

        // Secret should not be saved yet
        $this->assertNull($this->superAdmin->fresh()->two_fa_secret);
    }

    public function test_super_admin_can_enable_2fa(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;
        $service = app(SuperAdminService::class);
        $secret = $service->generateSecret();

        Cache::put("2fa_setup:{$this->superAdmin->id}", $secret, now()->addMinutes(10));

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/enable', [
                'code' => '000000', // Invalid code
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'INVALID_2FA_CODE');
        $this->assertNull($this->superAdmin->fresh()->two_fa_secret);
        $this->assertSame($secret, Cache::get("2fa_setup:{$this->superAdmin->id}"));
    }

    public function test_enable_2fa_requires_setup_first(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/enable', [
                'code' => '000000',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'SETUP_REQUIRED');
    }

    public function test_login_requires_2fa_code_when_enabled(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('error', 'TWO_FA_REQUIRED');
    }

    public function test_login_rejects_invalid_2fa_code(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
            'two_fa_code' => '123456', // Invalid code
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_2FA_CODE');
    }

    public function test_super_admin_can_disable_2fa_with_password(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/disable', [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_PASSWORD');
        $this->assertNotNull($this->superAdmin->fresh()->two_fa_secret);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/disable', [
                'password' => 'password123',
            ]);

        $response->assertOk();
        $this->assertNull($this->superAdmin->fresh()->two_fa_secret);
    }
}
