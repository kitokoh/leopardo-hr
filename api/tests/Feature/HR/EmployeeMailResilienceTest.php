<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1776 — La création d'employé avec invitation doit réussir même
 * quand le transport mail n'est pas configuré (MAIL_MAILER absent /
 * MAIL_URL vide → « Unsupported mail transport [] »), au lieu d'un 500.
 *
 * Le correctif vit dans UserInvitationService::createAndSend() : l'envoi
 * est enveloppé dans un try/catch + report() — l'invitation reste
 * enregistrée en base (resend possible) et le flux principal continue.
 */
class EmployeeMailResilienceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
        ]);
        $this->manager = $manager;
    }

    public function test_employee_creation_succeeds_when_mail_transport_missing(): void
    {
        // Reproduit l'environnement de l'issue : aucun transport mail valide.
        config()->set('mail.default', '');

        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Zohra',
            'last_name' => 'B',
            'email' => 'zohra@example.dz',
            'hire_date' => '2026-08-01',
            'role' => 'employee',
            'send_invitation' => true,
        ]);

        $response->assertCreated();

        // L'employé est bien créé…
        $this->assertDatabaseHas('employees', [
            'email' => 'zohra@example.dz',
            'company_id' => $this->company->id,
        ]);

        // …et l'invitation est enregistrée malgré l'échec de l'envoi
        // (elle pourra être renvoyée une fois le mailer configuré).
        $this->assertDatabaseHas('user_invitations', [
            'email' => 'zohra@example.dz',
        ]);
    }

    public function test_employee_creation_succeeds_when_mail_send_throws(): void
    {
        // Même scénario avec un mailer déclaré mais un transport invalide
        // (simule une panne SMTP au moment du send).
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => '127.0.0.1',
            'port' => 1,
            'timeout' => 1,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Ali',
            'last_name' => 'Said',
            'email' => 'ali.said@example.dz',
            'hire_date' => '2026-08-01',
            'role' => 'employee',
            'send_invitation' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employees', [
            'email' => 'ali.said@example.dz',
        ]);
    }

    public function test_invitation_resend_does_not_crash_when_mail_unavailable(): void
    {
        config()->set('mail.default', '');

        Sanctum::actingAs($this->manager);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'resend@example.dz',
        ]);

        $invitation = UserInvitation::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'email' => $employee->email,
            'schema_name' => $this->company->schema_name,
            'role' => $employee->role,
            'invited_by_type' => 'manager',
            'invited_by_email' => $this->manager->email,
            'token_hash' => hash('sha256', 'some-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson("/api/v1/invitations/{$invitation->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.email', 'resend@example.dz');
    }
}
