<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Dégradation de l'auto-login à l'activation par lien.
 *
 * `InvitationActivationService::activate()` (mode lien) enveloppe
 * `AuthService::login()` dans un `try/catch` : si la connexion automatique
 * échoue APRÈS que le compte a été activé, l'activation ne doit pas être
 * annulée — on dégrade vers une connexion manuelle (`token: null`).
 *
 * Cette branche n'était couverte par AUCUN test. Elle est pourtant le seul
 * endroit où le contrat HTTP de `POST /onboarding/invitation/{token}/activate`
 * rend un `201` avec `data.token = null` : un client qui suppose un jeton
 * toujours présent casse silencieusement.
 *
 * Le cas atteignable sans mock : un employé `suspended` active son invitation.
 * `accept()` réussit (le statut n'est jamais forcé — cf. test de non-lèvement
 * de suspension), puis `login()` lève `EmployeeNotActiveException` sur
 * `status !== 'active'`. L'activation doit rester acquise.
 */
class OnboardingActivationAutologinDegradationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->company = Company::factory()->create(['status' => 'active']);
        $this->manager = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $employeeAttributes
     * @return array{0: UserInvitation, 1: string}
     */
    private function inviteEmployee(array $employeeAttributes = []): array
    {
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ], $employeeAttributes));

        $token = app(UserInvitationService::class)->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->manager->email,
        )->token;

        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return [$invitation, $token];
    }

    /** @test */
    public function a_suspended_employee_activation_succeeds_but_returns_no_token(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'suspendu.autologin@company.test']);

        // Suspension APRÈS l'émission de l'invitation : le lien reste valide,
        // mais la connexion automatique doit échouer.
        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'suspended'])->save();

        $response = $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ]);

        // Contrat de dégradation : 201 (l'activation a réussi) + jeton nul.
        $response->assertStatus(201)
            ->assertJsonPath('message', 'ACCOUNT_ACTIVATED')
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.token_expires_at', null);

        // L'activation est ACQUISE malgré l'échec de connexion automatique.
        $employee->refresh();
        $this->assertNotNull(
            $employee->invitation_accepted_at,
            'L\'activation doit rester acquise même si l\'auto-login échoue.',
        );
        $this->assertNotNull($employee->email_verified_at);

        // Et la suspension n'a pas été levée au passage.
        $this->assertSame('suspended', $employee->status);

        // L'invitation est consommée : le lien ne peut pas être rejoué.
        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
    }

    /** @test */
    public function the_consumed_invitation_cannot_be_replayed_after_a_degraded_activation(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'rejoue.autologin@company.test']);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'suspended'])->save();

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        // Deuxième tentative : 410, jamais un second 201.
        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'AutreMotDePasse123!',
            'password_confirmation' => 'AutreMotDePasse123!',
        ])->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_ALREADY_ACCEPTED');
    }

    /** @test */
    public function a_suspended_employee_can_still_log_in_manually_once_reactivated(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'reactive.autologin@company.test']);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'suspended'])->save();

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201)->assertJsonPath('data.token', null);

        // Réactivation par le propriétaire : le mot de passe posé à
        // l'activation doit être celui qui permet la connexion manuelle.
        $employee->refresh();
        $employee->forceFill(['status' => 'active'])->save();

        // `AuthController::login` sérialise un `EmployeeResource` et ajoute le
        // jeton en clé RACINE (`additional()`), pas sous `data`.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'reactive.autologin@company.test',
            'password' => 'MotDePasseSolide123!',
        ])->assertOk();

        $this->assertIsString(
            $login->json('token'),
            'Le mot de passe posé à l\'activation doit permettre une connexion manuelle.',
        );
        $this->assertNotEmpty($login->json('token'));
    }
}
