<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests de non-régression du flux d'ONBOARDING (audit IAM).
 *
 * Chaque test couvre une anomalie corrigée dans ce lot :
 *  1. le jeton rendu par l'activation porte les abilities tenant ;
 *  2. une invitation déjà consommée n'est jamais rouverte par un resend ;
 *  3. l'activation est atomique (pas de compte activé + invitation ouverte) ;
 *  4. un employé archivé ne peut pas activer son invitation ;
 *  5. le `manager_role` de l'invitation est appliqué à l'activation ;
 *  6. `show` refuse une invitation d'une société suspendue.
 */
class OnboardingActivationRegressionTest extends TestCase
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
     * Crée une invitation ouverte pour un employé du tenant courant et
     * retourne [invitation, jeton en clair].
     *
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
    public function activation_returns_a_token_carrying_tenant_abilities(): void
    {
        [, $token] = $this->inviteEmployee(['email' => 'nouveau.rh@company.test']);

        $response = $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'device_name' => 'web',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'ACCOUNT_ACTIVATED');

        $plainToken = $response->json('data.token');
        $this->assertIsString($plainToken, 'L\'activation doit rendre un jeton utilisable.');

        // Le jeton doit porter les abilities tenant, sinon TenantMiddleware ne
        // peut pas résoudre le contexte et le dashboard répond 403.
        $accessToken = DB::table('personal_access_tokens')
            ->where('id', explode('|', $plainToken)[0])
            ->first();

        $this->assertNotNull($accessToken, 'Le jeton Sanctum doit exister en base.');

        $abilities = json_decode((string) $accessToken->abilities, true);
        $this->assertIsArray($abilities);

        $this->assertContains(
            'tenant_schema:'.$this->company->schema_name,
            $abilities,
            'Le jeton doit porter tenant_schema.'
        );
        $this->assertContains(
            'tenant_company:'.$this->company->id,
            $abilities,
            'Le jeton doit porter tenant_company.'
        );
    }

    /** @test */
    public function a_consumed_invitation_is_never_reopened_by_a_resend(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'deja.actif@company.test']);

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
        $acceptedAt = $invitation->accepted_at->toIso8601String();
        $oldHash = $invitation->token_hash;

        // Un resend sur une invitation consommée ne doit rien rouvrir.
        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $returned = app(UserInvitationService::class)->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->manager->email,
        );

        $this->assertSame('', $returned->token, 'Aucun jeton ne doit être émis pour une invitation consommée.');
        $this->assertFalse($returned->mailSent, 'Aucun courriel ne doit être annoncé envoyé pour une invitation consommée.');
        $this->assertFalse($returned->hasFallbackLink(), 'Aucun lien ne doit être exposé pour une invitation consommée.');

        $invitation->refresh();
        $this->assertSame($acceptedAt, $invitation->accepted_at?->toIso8601String());
        $this->assertSame($oldHash, $invitation->token_hash, 'Le jeton ne doit pas être rotationné.');

        // Et l'ancien lien reste inutilisable.
        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'AutreMotDePasse123!',
            'password_confirmation' => 'AutreMotDePasse123!',
        ])->assertStatus(410);
    }

    /** @test */
    public function activation_is_atomic_and_consumes_the_invitation(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'atomique@company.test']);

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at, 'L\'invitation doit être consommée.');

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $this->assertTrue(
            Hash::check('MotDePasseSolide123!', (string) $employee->password_hash),
            'Le mot de passe doit être persisté.'
        );
        $this->assertNotNull($employee->email_verified_at);
        $this->assertNotNull($employee->invitation_accepted_at);
    }

    /** @test */
    public function an_archived_employee_cannot_activate_their_invitation(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'parti@company.test']);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'archived'])->save();

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(410);

        $invitation->refresh();
        $this->assertNull($invitation->accepted_at, 'L\'invitation ne doit pas être consommée.');
    }

    /** @test */
    public function the_invitation_manager_role_is_applied_on_activation(): void
    {
        [$invitation, $token] = $this->inviteEmployee([
            'email' => 'rh.invite@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $this->assertSame('manager', $employee->role);
        $this->assertSame('rh', $employee->manager_role, 'Le manager_role de l\'invitation doit être appliqué.');
        $this->assertTrue($employee->isHr(), 'Un RH invité doit être reconnu comme RH.');
    }

    /** @test */
    public function show_refuses_an_invitation_from_a_suspended_company(): void
    {
        [, $token] = $this->inviteEmployee(['email' => 'suspendu@company.test']);

        $this->company->forceFill(['status' => 'suspended'])->save();

        $this->getJson("/api/v1/onboarding/invitation/{$token}")
            ->assertStatus(403)
            ->assertJsonPath('error', 'COMPANY_SUSPENDED');
    }
}
