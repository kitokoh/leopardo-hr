<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #7762 (spec MISSION_ESPACE_CLIENT §3.1) — révocation d'une invitation :
 *
 *  - DELETE /invitations/{id} supprime la ligne → le token du mail devient
 *    inutilisable immédiatement (l'activation répond 404) ;
 *  - une invitation déjà acceptée n'est pas révocable (410, comme resend) ;
 *  - gate manageInvitations (principal/rh) et isolation multi-tenant.
 */
class InvitationRevocationTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();
        $this->principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @return array{0: UserInvitation, 1: string} L'invitation et son token en clair. */
    private function invite(?Company $company = null): array
    {
        Mail::fake();

        $company ??= $this->company;
        $invitee = Employee::factory()->create(['company_id' => $company->id]);
        $inviter = $company->id === $this->company->id
            ? $this->principal
            : Employee::factory()->manager()->create(['company_id' => $company->id]);

        /** @var UserInvitationService $service */
        $service = app(UserInvitationService::class);
        $token = $service->createAndSend(
            company: $company,
            employee: $invitee,
            invitedByType: 'manager',
            invitedByEmail: $inviter->email,
        );

        /** @var UserInvitation $invitation */
        $invitation = UserInvitation::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $invitee->id)
            ->firstOrFail();

        return [$invitation, $token];
    }

    public function test_principal_revokes_a_pending_invitation_and_its_token_dies(): void
    {
        [$invitation, $token] = $this->invite();

        Sanctum::actingAs($this->principal);

        $this->deleteJson("/api/v1/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id);

        $this->assertDatabaseMissing('user_invitations', ['id' => $invitation->id]);

        // Le lien reçu par email est mort : l'activation ne trouve plus le token.
        /** @var UserInvitationService $service */
        $service = app(UserInvitationService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->accept($token, 'S3cret!Passw0rd');
    }

    public function test_accepted_invitation_is_not_revocable(): void
    {
        [$invitation, $token] = $this->invite();

        /** @var UserInvitationService $service */
        $service = app(UserInvitationService::class);
        $service->accept($token, 'S3cret!Passw0rd');

        Sanctum::actingAs($this->principal);

        $this->deleteJson("/api/v1/invitations/{$invitation->id}")
            ->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_ALREADY_ACCEPTED');

        $this->assertDatabaseHas('user_invitations', ['id' => $invitation->id]);
    }

    public function test_revocation_is_gated_and_tenant_isolated(): void
    {
        [$invitation] = $this->invite();

        // Un employé non-manager ne révoque pas.
        Sanctum::actingAs(Employee::factory()->create(['company_id' => $this->company->id]));
        $this->deleteJson("/api/v1/invitations/{$invitation->id}")->assertStatus(403);

        // Le principal d'une AUTRE société ne voit pas l'invitation (404).
        $otherCompany = Company::factory()->create();
        $otherPrincipal = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($otherPrincipal);
        $this->deleteJson("/api/v1/invitations/{$invitation->id}")->assertStatus(404);

        $this->assertDatabaseHas('user_invitations', ['id' => $invitation->id]);
    }
}
