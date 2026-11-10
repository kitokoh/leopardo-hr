<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Convergence des DEUX chemins d'activation d'invitation.
 *
 * `POST /auth/register` et `POST /onboarding/invitation/{token}/activate`
 * partagent désormais `InvitationActivationContract`. Ces tests verrouillent les
 * trois divergences qui existaient avant la convergence :
 *
 *  1. `/auth/register` rendait un jeton SANS abilities tenant → 403 sur le
 *     dashboard alors que l'activation avait réussi ;
 *  2. `/auth/register` ignorait les accès ressource pré-assignés (#7601) ;
 *  3. `/auth/register` ignorait le `manager_role` porté par l'invitation.
 *
 * Plus un test de parité : les deux surfaces produisent le MÊME état de
 * compte pour la même invitation.
 */
class OnboardingActivationConvergenceTest extends TestCase
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
    public function register_returns_a_token_carrying_tenant_abilities(): void
    {
        [, $token] = $this->inviteEmployee(['email' => 'register.abilities@company.test']);

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Nouveau',
            'last_name' => 'Collaborateur',
            'email' => 'register.abilities@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ]);

        $response->assertStatus(201);

        $plainToken = $response->json('token');
        $this->assertIsString($plainToken);

        $accessToken = DB::table('personal_access_tokens')
            ->where('id', explode('|', $plainToken)[0])
            ->first();

        $this->assertNotNull($accessToken, 'Le jeton Sanctum doit exister en base.');

        $abilities = json_decode((string) $accessToken->abilities, true);
        $this->assertIsArray($abilities);

        // Sans ces abilities, TenantMiddleware ne résout pas le contexte et
        // l'employé inscrit reçoit un 403 sur son dashboard.
        $this->assertContains('tenant_schema:'.$this->company->schema_name, $abilities);
        $this->assertContains('tenant_company:'.$this->company->id, $abilities);
        $this->assertContains('tenant_employee:'.$response->json('data.id'), $abilities);
    }

    /** @test */
    public function register_applies_the_invitation_manager_role(): void
    {
        [, $token] = $this->inviteEmployee([
            'email' => 'register.rh@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Rania',
            'last_name' => 'Rh',
            'email' => 'register.rh@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(201);

        $employee = Employee::query()->where('email', 'register.rh@company.test')->firstOrFail();

        $this->assertSame('manager', $employee->role);
        $this->assertSame('rh', $employee->manager_role, 'Le manager_role de l\'invitation doit être appliqué.');
        $this->assertTrue($employee->isHr(), 'Un RH invité doit être reconnu comme RH.');
    }

    /** @test */
    public function register_materialises_pre_assigned_resource_access(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'register.scope@company.test']);

        // Une ressource réelle du tenant : le registre refuse un identifiant
        // inexistant (fail-closed), donc on en crée une.
        $site = Site::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Établissement Principal',
            'code' => 'SITE-1',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $invitation->metadata = array_merge((array) $invitation->metadata, [
            'resource_assignments' => [
                ['resource_type' => 'site', 'resource_id' => (int) $site->id, 'access_level' => 'manage'],
            ],
        ]);
        $invitation->save();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Sami',
            'last_name' => 'Perimetre',
            'email' => 'register.scope@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(201);

        $employee = Employee::query()->where('email', 'register.scope@company.test')->firstOrFail();

        // #7601 — l'accès pré-assigné prend effet à l'activation, quel que
        // soit le chemin emprunté.
        $this->assertDatabaseHas('employee_resource_assignments', [
            'employee_id' => $employee->id,
            'resource_type' => 'site',
            'resource_id' => (int) $site->id,
            'access_level' => 'manage',
        ]);
    }

    /** @test */
    public function both_activation_paths_produce_the_same_account_state(): void
    {
        // Chemin A — /auth/register
        [, $registerToken] = $this->inviteEmployee([
            'email' => 'parite.register@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Parite',
            'last_name' => 'Register',
            'email' => 'parite.register@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $registerToken,
        ])->assertStatus(201);

        // Chemin B — /onboarding/invitation/{token}/activate
        [, $activateToken] = $this->inviteEmployee([
            'email' => 'parite.activate@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        $this->postJson("/api/v1/onboarding/invitation/{$activateToken}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $viaRegister = Employee::query()->where('email', 'parite.register@company.test')->firstOrFail();
        $viaActivate = Employee::query()->where('email', 'parite.activate@company.test')->firstOrFail();

        // Même état de compte : rôle, rôle managérial, statut, vérifications.
        foreach ([$viaRegister, $viaActivate] as $employee) {
            $this->assertSame('manager', $employee->role);
            $this->assertSame('rh', $employee->manager_role);
            $this->assertSame('active', $employee->status);
            $this->assertNotNull($employee->email_verified_at);
            $this->assertNotNull($employee->invitation_accepted_at);
        }
    }

    /** @test */
    public function activation_never_lifts_a_suspension(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'suspendu.activation@company.test']);

        // Un employé suspendu ne doit pas pouvoir se réactiver en activant son
        // invitation : ce serait un contournement de la suspension.
        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'suspended'])->save();

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ]);

        $employee->refresh();

        $this->assertSame('suspended', $employee->status, 'L\'activation ne doit jamais lever une suspension.');
    }

    /** @test */
    public function register_still_rejects_an_unusable_invitation_with_the_historical_contract(): void
    {
        // Contrat inchangé : 422 REGISTRATION_NOT_AVAILABLE, jamais un 404/410
        // qui révélerait l'état exact de l'invitation sur une route publique.
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Sans',
            'last_name' => 'Invitation',
            'email' => 'sans.invitation@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => 'jeton-inexistant',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        $this->assertDatabaseMissing('employees', ['email' => 'sans.invitation@company.test']);
    }

    /** @test */
    public function register_rejects_an_invitation_issued_for_another_email(): void
    {
        [, $token] = $this->inviteEmployee(['email' => 'titulaire@company.test']);

        // Le jeton est valide, mais l'e-mail soumis ne correspond pas : aucune
        // activation possible (pas de détournement d'invitation).
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Usurpateur',
            'last_name' => 'Test',
            'email' => 'autre.personne@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        $this->assertDatabaseMissing('employees', ['email' => 'autre.personne@company.test']);
    }
}
