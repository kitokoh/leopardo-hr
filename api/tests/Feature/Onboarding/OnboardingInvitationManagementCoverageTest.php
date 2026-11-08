<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Couverture des BRANCHES de gestion d'invitation restées sans test.
 *
 * Les suites existantes couvrent le parcours nominal et les cas limites du
 * cycle de vie (`OnboardingLifecycleEdgeCasesTest`, `OnboardingActivationRegressionTest`,
 * `InvitationRevocationTest`). Ce fichier verrouille les branches d'ERREUR et
 * de LISTING qui n'étaient exercées par aucun test :
 *
 *  - `InvitationController::index` — listing des invitations du tenant ;
 *  - `InvitationController::resend` — refus 410 d'une invitation déjà acceptée
 *    (le service `createAndSend` renvoie `''`, mais le contrôleur doit court-
 *    circuiter AVANT, avec le contrat d'erreur `INVITATION_ALREADY_ACCEPTED`) ;
 *  - `RoleAssignmentController::assign` — refus 404 d'un employé d'une AUTRE
 *    entreprise (isolation inter-tenant du geste d'assignation) ;
 *  - `RoleAssignmentController::teamRoles` — listing des rôles d'équipe, et
 *    refus 403 d'un non-principal.
 *
 * Aucun de ces tests ne modifie le code applicatif : ils verrouillent le
 * comportement réel observé pour empêcher une régression future.
 */
class OnboardingInvitationManagementCoverageTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->company = Company::factory()->create(['status' => 'active']);
        $this->principal = Employee::factory()->manager()->create([
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
            invitedByEmail: $this->principal->email,
        );

        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return [$invitation, $token];
    }

    // ---------------------------------------------------------------------
    // InvitationController::index — listing
    // ---------------------------------------------------------------------

    /** @test */
    public function a_principal_can_list_the_company_invitations(): void
    {
        [$invitation] = $this->inviteEmployee(['email' => 'liste@company.test']);

        Sanctum::actingAs($this->principal);

        $response = $this->getJson('/api/v1/invitations');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $invitation->id);
        $response->assertJsonPath('data.0.email', 'liste@company.test');
        $response->assertJsonPath('data.0.status', 'pending');
    }

    /** @test */
    public function a_plain_employee_cannot_list_the_company_invitations(): void
    {
        $this->inviteEmployee(['email' => 'prive@company.test']);

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/invitations')->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // InvitationController::resend — refus d'une invitation consommée
    // ---------------------------------------------------------------------

    /** @test */
    public function resending_an_accepted_invitation_is_refused_with_410(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'consommee@company.test']);

        // L'invitation est consommée par une activation réelle.
        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
        $acceptedAt = $invitation->accepted_at->toIso8601String();
        $oldHash = $invitation->token_hash;

        Sanctum::actingAs($this->principal);

        $this->postJson("/api/v1/invitations/{$invitation->id}/resend")
            ->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_ALREADY_ACCEPTED');

        // Rien n'a été rouvert : ni le jeton, ni la date d'acceptation.
        $invitation->refresh();
        $this->assertSame($acceptedAt, $invitation->accepted_at?->toIso8601String());
        $this->assertSame($oldHash, $invitation->token_hash);
    }

    /** @test */
    public function resending_a_pending_invitation_rotates_its_token(): void
    {
        [$invitation, $oldToken] = $this->inviteEmployee(['email' => 'en.attente@company.test']);

        $oldHash = $invitation->token_hash;

        Sanctum::actingAs($this->principal);

        $this->postJson("/api/v1/invitations/{$invitation->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id);

        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash, 'Le resend doit rotationner le jeton.');
        $this->assertNull($invitation->accepted_at);

        // L'ancien lien est mort, le nouveau est vivant.
        $this->postJson("/api/v1/onboarding/invitation/{$oldToken}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(404);
    }

    // ---------------------------------------------------------------------
    // RoleAssignmentController::assign — isolation inter-tenant
    // ---------------------------------------------------------------------

    /** @test */
    public function a_principal_cannot_assign_a_role_to_an_employee_of_another_company(): void
    {
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($this->principal);

        $this->postJson("/api/v1/employees/{$foreignEmployee->id}/assign-role", [
            'manager_role' => 'rh',
        ])->assertStatus(404);

        // Le rôle de l'employé étranger n'a pas bougé.
        $foreignEmployee->refresh();
        $this->assertNull($foreignEmployee->manager_role);
    }

    // ---------------------------------------------------------------------
    // RoleAssignmentController::teamRoles — listing et garde
    // ---------------------------------------------------------------------

    /** @test */
    public function a_principal_can_list_the_team_roles(): void
    {
        $hr = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->principal);

        $response = $this->getJson('/api/v1/company/team-roles');

        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($hr->id, $ids);
        $this->assertContains($this->principal->id, $ids);
    }

    /** @test */
    public function a_non_principal_manager_cannot_list_the_team_roles(): void
    {
        $deptManager = Employee::factory()->managerDept()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($deptManager);

        $this->getJson('/api/v1/company/team-roles')->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // Révocation de rôle — l'accès tombe avec le MÊME jeton
    // ---------------------------------------------------------------------

    /**
     * Scénario de la matrice de tests : « la révocation d'un rôle retire
     * effectivement les accès ».
     *
     * Les abilities Sanctum ne portent PAS le `manager_role` (elles ne
     * contiennent que `tenant_schema:` / `tenant_company:` / `tenant_employee:`).
     * L'autorisation relit donc la base à chaque requête : révoquer le rôle
     * doit suffire à faire tomber l'accès, sans rotation de jeton. Ce test
     * verrouille cette propriété — une future mise en cache du rôle dans les
     * abilities la casserait.
     */
    /** @test */
    public function revoking_a_role_removes_access_for_the_same_token(): void
    {
        $hr = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);

        // Le RH peut lister les invitations avec son jeton.
        $hrToken = $hr->createToken('tests')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$hrToken}")
            ->getJson('/api/v1/invitations')
            ->assertOk();

        // Le principal révoque le rôle RH.
        Sanctum::actingAs($this->principal);
        $this->postJson("/api/v1/employees/{$hr->id}/assign-role", [
            'manager_role' => null,
        ])->assertOk();

        $hr->refresh();
        $this->assertNull($hr->manager_role);

        // Le MÊME jeton n'a plus accès : l'autorisation relit la base.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$hrToken}")
            ->getJson('/api/v1/invitations')
            ->assertStatus(403);
    }
}
