<?php

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2626) : flux forgot/reset password —
 * token haché 60 min, usage unique, révocation des tokens Sanctum existants,
 * réponse générique anti-énumération.
 */
class PasswordResetTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        $this->assertInstanceOf(Company::class, $company);
        $this->company = $company;
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'reset.me@example.com',
            'password_hash' => Hash::make('old-password'),
            'role' => 'manager',
            'status' => 'active',
        ]);
        $this->assertInstanceOf(Employee::class, $employee);
        $this->employee = $employee;

        // Dispatch table cross-tenant (public.user_lookups) — pattern AuthService.
        if (DB::getSchemaBuilder()->hasTable('user_lookups')) {
            DB::table('user_lookups')->updateOrInsert(
                ['email' => 'reset.me@example.com'],
                [
                    'company_id' => $this->company->id,
                    'schema_name' => 'shared_tenants',
                    'employee_id' => $this->employee->id,
                    'role' => 'manager',
                ]
            );
        }
    }

    public function test_forgot_password_sends_mail_and_returns_generic_response(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset.me@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'PASSWORD_RESET_SENT']);

        Mail::assertSent(PasswordResetMail::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset.me@example.com',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_forgot_password_does_not_leak_unknown_emails(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'PASSWORD_RESET_SENT']);

        Mail::assertNothingSent();
    }

    public function test_reset_password_updates_hash_and_revokes_tokens(): void
    {
        $token = 'reset-token-'.str()->random(32);

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset.me@example.com',
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->employee->createToken('legacy-device');

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset.me@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'PASSWORD_RESET_DONE']);

        $this->employee->refresh();
        $this->assertTrue(Hash::check('new-password-123', (string) $this->employee->password_hash));

        // Token marqué utilisé + tokens Sanctum révoqués.
        $this->assertNotNull(DB::table('password_reset_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->value('used_at'));
        $this->assertSame(0, $this->employee->tokens()->count());
    }

    public function test_reset_password_rejects_used_token(): void
    {
        $token = 'reset-token-used';

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset.me@example.com',
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(60),
            'used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset.me@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422);
    }
}
