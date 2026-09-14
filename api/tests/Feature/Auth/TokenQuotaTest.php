<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7009 — quota de tokens Sanctum actifs par utilisateur.
 *
 * Chaque POST /auth/login crée un token sans purge : sur DEV, le compte
 * démo principal accumulait 631 tokens valides. Depuis le correctif, seuls
 * les `max_active_tokens_per_user` tokens les plus récents sont conservés
 * après chaque login (TokenQuotaService, appelé par AuthService::login).
 */
class TokenQuotaTest extends TestCase
{
    use RefreshTenantDatabase;

    private function registerAccount(string $email, string $invitationToken): int
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Quota',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'password123456',
            'password_confirmation' => 'password123456',
            'invitation_token' => $invitationToken,
        ]);
        $register->assertCreated();

        return (int) $register->json('data.id');
    }

    private function createInvitation(string $email, string $token = 'quota-token-123'): string
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('old-password'),
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        DB::table('public.user_invitations')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => $employee->id,
            'email' => $email,
            'role' => 'ordinary',
            'manager_role' => null,
            'invited_by_type' => 'platform',
            'invited_by_email' => 'admin@leopardo-rh.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    private function loginCount(string $email, int $times): int
    {
        for ($i = 0; $i < $times; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'password123456',
                'device_name' => 'quota-device-'.$i,
            ])->assertOk()->assertJsonStructure(['token']);
        }

        return $times;
    }

    public function test_login_keeps_only_latest_tokens_within_quota(): void
    {
        config()->set('auth.max_active_tokens_per_user', 5);
        $email = 'quota.user@example.com';

        $employeeId = $this->registerAccount($email, $this->createInvitation($email));
        $this->loginCount($email, 8);

        $count = PersonalAccessToken::query()
            ->where('tokenable_id', $employeeId)
            ->where('tokenable_type', (new Employee)->getMorphClass())
            ->count();

        $this->assertSame(5, $count, 'seuls les 5 tokens les plus récents doivent subsister');
    }

    public function test_quota_zero_disables_pruning(): void
    {
        config()->set('auth.max_active_tokens_per_user', 0);
        $email = 'quota.disabled@example.com';

        $employeeId = $this->registerAccount($email, $this->createInvitation($email, 'quota-disabled-token'));
        $this->loginCount($email, 8);

        $count = PersonalAccessToken::query()
            ->where('tokenable_id', $employeeId)
            ->where('tokenable_type', (new Employee)->getMorphClass())
            ->count();

        $this->assertSame(9, $count, 'quota 0 = pas de purge (1 token register + 8 logins conservés)');
    }
}
