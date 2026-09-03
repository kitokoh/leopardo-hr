<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6751 — POST /auth/forgot-password :
 *  - un échec de transport email (SMTP down) ne doit NI produire un 500 NI
 *    différencier la réponse (anti-énumération 200 inconnu vs 500 existant) ;
 *  - le token est tout de même inséré en base (réessai possible côté ops).
 */
class ForgotPasswordNoEnumerationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function existingEmployee(): Employee
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'country' => 'DZ',
        ]);

        /** @var Employee $employee */
        $employee = new Employee(['email' => 'fatima.meziane@techcorp-algerie.dz']);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Fatima',
            'last_name' => 'Meziane',
        ])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ])->save();

        return $employee;
    }

    public function test_unknown_email_returns_200_anti_enumeration(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inconnu-xyz@test.invalid'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'PASSWORD_RESET_SENT');
    }

    public function test_existing_email_with_mail_transport_failure_still_returns_200(): void
    {
        // #6751 : l'envoi SMTP échoue (transport down) → le parcours ne doit
        // pas 500 : réponse anti-énumération identique à l'email inconnu.
        $this->existingEmployee();

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Failed to authenticate on SMTP server'));

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'fatima.meziane@techcorp-algerie.dz'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'PASSWORD_RESET_SENT');

        // Le token est quand même inséré en base (réessai possible).
        $this->assertDatabaseHas('public.password_reset_tokens', [
            'email' => 'fatima.meziane@techcorp-algerie.dz',
        ]);
    }

    public function test_existing_email_with_mail_ok_returns_200_and_inserts_token(): void
    {
        Mail::fake();

        $this->existingEmployee();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'fatima.meziane@techcorp-algerie.dz'])
            ->assertOk()
            ->assertJsonPath('message', 'PASSWORD_RESET_SENT');

        $this->assertDatabaseHas('public.password_reset_tokens', [
            'email' => 'fatima.meziane@techcorp-algerie.dz',
        ]);
    }
}
