<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Cas limites du CYCLE DE VIE d'onboarding, pour les trois populations.
 *
 * Complète `OnboardingActivationRegressionTest` (anomalies A1–A6) et
 * `OnboardingActivationConvergenceTest` (convergence des deux chemins) sur les
 * états que le parcours nominal ne traverse pas :
 *
 *  - employé invité : invitation EXPIRÉE, invitation RÉVOQUÉE (départ),
 *    changement de rôle avant activation, suspension puis réactivation,
 *    départ d'un employé déjà actif ;
 *  - propriétaire de tenant : le premier propriétaire est bien `principal`,
 *    et un second `principal` ne peut pas être créé par une simple invitation ;
 *  - grand public : un compte consommateur ne peut pas atteindre les surfaces
 *    privées d'un professionnel (isolation inter-guards).
 *
 * Aucun de ces tests ne modifie le code applicatif : ils verrouillent le
 * comportement réel observé (fail-closed) pour empêcher une régression future.
 */
class OnboardingLifecycleEdgeCasesTest extends TestCase
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
     * Crée un employé du tenant courant et son invitation ouverte.
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

    // ---------------------------------------------------------------------
    // Population 2 — employé invité : états du cycle de vie
    // ---------------------------------------------------------------------

    /** @test */
    public function an_expired_invitation_cannot_be_shown_nor_activated(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'expire@company.test']);

        // L'invitation est datée dans le passé : elle est expirée.
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        $this->getJson("/api/v1/onboarding/invitation/{$token}")
            ->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_EXPIRED');

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_EXPIRED');

        $invitation->refresh();
        $this->assertNull($invitation->accepted_at, 'Une invitation expirée ne doit pas être consommée.');
    }

    /** @test */
    public function the_invitation_schema_forbids_a_missing_expiry(): void
    {
        // #7864 (D6) : le service traite `expires_at` null comme expiré
        // (fail-closed). Cette branche est INATTEIGNABLE en pratique : le schéma
        // impose `expires_at NOT NULL`. On verrouille l'invariant au niveau base
        // — une invitation sans expiration ne peut pas exister, donc le
        // fail-closed ne peut jamais être contourné par une ligne sans date.
        $isNullable = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'user_invitations')
            ->where('column_name', 'expires_at')
            ->value('is_nullable');

        $this->assertSame('NO', $isNullable, 'expires_at doit être NOT NULL (invitation toujours datée).');
    }

    /** @test */
    public function a_departed_employee_invitation_is_revoked(): void
    {
        // #7864 (D5) : un employé `departed` ne peut plus activer — l'invitation
        // est de fait révoquée (410 INVITATION_REVOKED), sans être consommée.
        [$invitation, $token] = $this->inviteEmployee(['email' => 'parti.departed@company.test']);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'departed'])->save();

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_REVOKED');

        $invitation->refresh();
        $this->assertNull($invitation->accepted_at, 'L\'invitation révoquée ne doit pas être consommée.');
    }

    /** @test */
    public function a_role_change_before_activation_is_applied_at_activation(): void
    {
        // Le rôle porté par l'invitation est relu à l'ACTIVATION : un changement
        // de rôle entre l'envoi et l'activation doit être celui qui prend effet.
        [$invitation, $token] = $this->inviteEmployee([
            'email' => 'changement.role@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        // L'opérateur change le rôle de l'employé avant qu'il n'active.
        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['role' => 'manager', 'manager_role' => 'comptable'])->save();

        // Une ré-invitation (resend) recopie le rôle courant dans l'invitation.
        $newToken = app(UserInvitationService::class)->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->manager->email,
        )->token;
        $this->assertNotSame('', $newToken, 'Un resend sur une invitation ouverte doit émettre un jeton.');

        $this->postJson("/api/v1/onboarding/invitation/{$newToken}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $employee->refresh();
        $this->assertSame('comptable', $employee->manager_role, 'Le rôle courant doit être appliqué à l\'activation.');
        $this->assertTrue($employee->isComptable(), 'Le rôle managérial doit être reconnu.');
        $this->assertFalse($employee->isHr(), 'L\'ancien rôle RH ne doit plus être actif.');
    }

    /** @test */
    public function a_suspended_employee_cannot_reactivate_via_a_fresh_invitation(): void
    {
        // Un employé suspendu ne doit pas contourner sa suspension en recevant
        // une nouvelle invitation : l'activation ne lève jamais la suspension.
        [$invitation, $token] = $this->inviteEmployee(['email' => 'suspendu.reinvite@company.test']);

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
    public function a_departed_active_employee_cannot_log_in(): void
    {
        // Départ d'un employé DÉJÀ actif : le login doit être refusé (fail-closed),
        // indépendamment de toute invitation.
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'actif.puis.parti@company.test',
            'password_hash' => Hash::make('MotDePasseSolide123!'),
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        // Avant le départ : le login fonctionne.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'actif.puis.parti@company.test',
            'password' => 'MotDePasseSolide123!',
        ])->assertStatus(200);

        $employee->forceFill(['status' => 'departed'])->save();

        // Après le départ : le login est refusé.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'actif.puis.parti@company.test',
            'password' => 'MotDePasseSolide123!',
        ])->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // Population 1 — propriétaire de tenant
    // ---------------------------------------------------------------------

    /** @test */
    public function the_first_tenant_owner_is_the_principal_manager(): void
    {
        // Le premier propriétaire d'un tenant est `manager`/`principal` : c'est
        // lui qui détient le contexte d'administration de son entreprise.
        $owner = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'email' => 'proprietaire@company.test',
        ]);

        $this->assertSame('manager', $owner->role);
        $this->assertSame('principal', $owner->manager_role);
        $this->assertTrue($owner->isPrincipal(), 'Le premier propriétaire doit être principal.');
    }

    /** @test */
    public function a_second_principal_cannot_be_created_by_a_plain_invitation(): void
    {
        // Une invitation ordinaire ne doit pas fabriquer un second `principal` :
        // le rôle managérial porté par l'invitation est celui de l'employé, et
        // une invitation RH ne produit jamais un principal.
        [$invitation, $token] = $this->inviteEmployee([
            'email' => 'second.principal@company.test',
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $this->assertSame('rh', $employee->manager_role);
        $this->assertFalse($employee->isPrincipal(), 'Une invitation RH ne doit pas produire un principal.');

        // Un seul principal dans le tenant : celui du setUp.
        $principals = Employee::query()
            ->where('company_id', $this->company->id)
            ->where('manager_role', 'principal')
            ->count();
        $this->assertSame(1, $principals, 'Le tenant ne doit compter qu\'un seul principal.');
    }

    // ---------------------------------------------------------------------
    // Population 3 — grand public : isolation inter-guards
    // ---------------------------------------------------------------------

    /** @test */
    public function a_public_consumer_token_cannot_reach_employee_surfaces(): void
    {
        // Un compte grand public (guard `travel_customer`) ne doit jamais
        // atteindre une surface privée d'employé (guard `sanctum`).
        $account = TravelCustomerAccount::query()->create([
            'name' => 'Client Public',
            'email' => 'client.public@example.test',
            'password' => Hash::make('MotDePasseSolide123!'),
            'last_login_at' => now(),
        ]);

        $token = $account->createToken('travel-web')->plainTextToken;

        // `/auth/me` est servi sous `auth:sanctum` (guard employés) : le jeton
        // public ne doit pas y donner accès.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /** @test */
    public function a_public_consumer_registration_creates_no_employee_and_no_tenant(): void
    {
        // S'inscrire comme consommateur ne doit créer NI employé NI entreprise :
        // le grand public n'a pas besoin de créer une entreprise.
        $employeesBefore = Employee::query()->count();
        $companiesBefore = Company::query()->count();

        $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => 'Nouveau Client',
            'email' => 'nouveau.client@example.test',
            'password' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $this->assertSame($employeesBefore, Employee::query()->count(), 'Aucun employé ne doit être créé.');
        $this->assertSame($companiesBefore, Company::query()->count(), 'Aucune entreprise ne doit être créée.');
        $this->assertDatabaseHas('travel_customer_accounts', ['email' => 'nouveau.client@example.test']);
    }

    /** @test */
    public function an_employee_token_cannot_reach_the_public_consumer_surface(): void
    {
        // Réciproque de l'isolation inter-guards : un jeton employé (guard
        // `sanctum`) ne doit pas ouvrir la surface du compte grand public
        // (guard `travel_customer`). Les deux populations ne partagent jamais
        // leurs jetons.
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'employe.isole@company.test',
            'password_hash' => Hash::make('MotDePasseSolide123!'),
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        $employeeToken = $employee->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$employeeToken)
            ->getJson('/api/v1/public/travel/marketplace/account/me')
            ->assertStatus(401);
    }
}
