<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Mail\PasswordResetMail;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2626 — réinitialisation de mot de passe :
 * demande (réponse générique anti-énumération), reset valide (mdp changé +
 * tokens Sanctum révoqués + jeton consommé), jeton expiré/réutilisé → 422.
 */
class PasswordResetTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'reset-me@example.com',
            'password_hash' => Hash::make('old-password'),
        ]);
        $this->employee = $employee;
    }

    private function issueToken(string $email, ?string $expiresAt = null): string
    {
        $token = 'reset-token-'.str()->random(20);
        DB::table('public.password_reset_tokens')->insert([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt ?? now()->addMinutes(60),
            'used_at' => null,
            'created_at' => now(),
        ]);

        return $token;
    }

    public function test_forgot_password_returns_generic_response_and_sends_mail(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset-me@example.com'])
            ->assertOk();

        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo('reset-me@example.com'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'reset-me@example.com']);
    }

    public function test_forgot_password_does_not_enumerate_unknown_email(): void
    {
        Mail::fake();

        // Même réponse (200 générique) pour un email inconnu — aucune fuite.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk();

        Mail::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $token = $this->issueToken('reset-me@example.com');

        // Un token Sanctum existant à révoquer.
        $oldToken = $this->employee->createToken('old-session');
        $this->assertCount(1, $this->employee->tokens()->get());

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->employee->refresh();
        $this->assertTrue(Hash::check('new-password-123', $this->employee->password_hash));
        $this->assertCount(0, $this->employee->tokens()->get(), 'Les tokens Sanctum existants doivent être révoqués.');

        $this->assertNotNull(
            DB::table('public.password_reset_tokens')
                ->where('email', 'reset-me@example.com')
                ->where('token_hash', hash('sha256', $token))
                ->value('used_at'),
            'Le jeton doit être marqué consommé.'
        );
    }

    public function test_reset_password_rejects_reused_token(): void
    {
        $token = $this->issueToken('reset-me@example.com');

        // Première utilisation → OK.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        // Réutilisation du même jeton → 422.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.com',
            'token' => $token,
            'password' => 'another-password-456',
            'password_confirmation' => 'another-password-456',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_RESET_TOKEN');
    }

    public function test_reset_password_rejects_expired_token(): void
    {
        $token = $this->issueToken('reset-me@example.com', now()->subMinute()->toDateTimeString());

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_RESET_TOKEN');
    }

    /**
     * #3363 — un employé d'un tenant à SCHÉMA (search_path ≠ shared_tenants)
     * doit pouvoir réinitialiser son mot de passe : forgot() émet le jeton et
     * reset() met à jour l'employé DANS son schéma (via public.user_lookups).
     */
    public function test_password_reset_works_for_schema_tenant_employee(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Le test tenant à schéma nécessite PostgreSQL.');
        }

        Mail::fake();

        $schema = 'reset_tenant_schema';
        DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
        DB::statement('CREATE SCHEMA '.$schema);
        DB::statement('CREATE TABLE '.$schema.'.employees (LIKE shared_tenants.employees INCLUDING ALL)');

        // Le mode tenancy_type='schema' est gelé par le modèle (Enterprise),
        // mais la résolution du reset suit le schema_name du user_lookups —
        // on simule donc un employé vivant dans un schéma réel.
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => $schema,
            'status' => 'active',
        ]);

        $employeeId = DB::table($schema.'.employees')->insertGetId([
            'company_id' => $company->id,
            'first_name' => 'Schema',
            'last_name' => 'Tenant',
            'email' => 'schema-tenant@example.com',
            'password_hash' => Hash::make('old-password'),
            'role' => 'manager',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('public.user_lookups')->updateOrInsert(
            ['email' => 'schema-tenant@example.com'],
            [
                'company_id' => $company->id,
                'schema_name' => $schema,
                'employee_id' => $employeeId,
                'role' => 'manager',
            ]
        );

        // forgot() doit résoudre l'employé via le lookup et émettre le mail.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'schema-tenant@example.com'])
            ->assertOk();

        $rawToken = null;
        Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$rawToken) {
            $rawToken = $mail->token;

            return $mail->hasTo('schema-tenant@example.com');
        });
        $this->assertNotNull($rawToken, 'Un jeton doit être créé pour un employé de tenant à schéma.');

        $this->assertNotNull(
            DB::table('public.password_reset_tokens')
                ->where('email', 'schema-tenant@example.com')
                ->value('token_hash'),
            'Le hash du jeton doit être persisté.'
        );

        // reset() doit mettre à jour l'employé DANS son schéma.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'schema-tenant@example.com',
            'token' => $rawToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(
            Hash::check(
                'new-password-123',
                (string) DB::table($schema.'.employees')->where('id', $employeeId)->value('password_hash')
            ),
            'Le mot de passe doit être mis à jour dans le schéma tenant.'
        );

        DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
    }
}
