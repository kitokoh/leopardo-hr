<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Couverture de la surface WEB de gestion des invitations (manager RH/Principal).
 *
 * `App\Http\Controllers\Web\InvitationManagementController` expose deux routes
 * (`GET /hr/invitations`, `POST /hr/invitations/{invitation}/resend`) protegees
 * par `auth:web` + `tenant` + `manager_role:principal,rh`. AUCUN test ne les
 * exercait : la surface n'etait couverte que par sa jumelle API
 * (`OnboardingInvitationManagementCoverageTest`), qui emprunte un autre
 * controleur, un autre guard et un autre contrat d'erreur.
 *
 * Ce fichier verrouille les invariants qui ne peuvent PAS etre deduits de la
 * surface API :
 *
 *  - le listing est borne au tenant de l'acteur (isolation inter-tenant) ;
 *  - un employe simple est refuse (403) par `manager_role` ;
 *  - le renvoi d'une invitation d'une AUTRE entreprise est introuvable (404) ;
 *  - le renvoi d'une invitation deja acceptee est refuse sans rotation ;
 *  - le renvoi d'une invitation ouverte rotationne le jeton ;
 *  - mailer KO => le lien est relaye au manager (branche #9322 cote web).
 *
 * Aucun test ne modifie le code applicatif : ils figent le comportement reel.
 */
class OnboardingWebInvitationManagementTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        // Pas de `Mail::fake()` ici : le fake remplace le manager AVANT la
        // resolution du transport, donc `send()` ne peut jamais lever et la
        // branche « mailer KO » resterait inatteignable. `phpunit.xml` fixe
        // `MAIL_MAILER=array` ; les tests qui ont besoin d'un echec cassent la
        // config localement (cf. `test_the_web_resend_relays_the_link_...`).

        $this->company = Company::factory()->create(['status' => 'active']);
        $this->principal = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Cree un employe du tenant courant et son invitation ouverte.
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

        $dispatch = app(UserInvitationService::class)->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->principal->email,
        );

        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return [$invitation, $dispatch->token];
    }

    // ---------------------------------------------------------------------
    // GET /hr/invitations — listing
    // ---------------------------------------------------------------------

    public function test_a_principal_can_open_the_web_invitations_page(): void
    {
        [$invitation] = $this->inviteEmployee(['email' => 'web.list@company.test']);

        $response = $this->actingAs($this->principal, 'web')->get('/hr/invitations');

        $response->assertOk();
        $response->assertSee('web.list@company.test');
        $response->assertSee('Invitations envoyees');
        $this->assertSame($this->company->id, $invitation->company_id);
    }

    public function test_an_rh_manager_can_open_the_web_invitations_page(): void
    {
        $rh = Employee::factory()->managerRh()->create([
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($rh, 'web')
            ->get('/hr/invitations')
            ->assertOk();
    }

    public function test_a_plain_employee_is_forbidden_from_the_web_invitations_page(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        $this->actingAs($employee, 'web')
            ->get('/hr/invitations')
            ->assertForbidden();
    }

    /**
     * Isolation inter-tenant : le listing ne doit JAMAIS laisser fuiter les
     * invitations d'une autre entreprise, meme quand l'acteur en connait
     * l'existence.
     */
    public function test_the_web_listing_never_leaks_another_company_invitations(): void
    {
        $this->inviteEmployee(['email' => 'mine@company.test']);

        $otherCompany = Company::factory()->create(['status' => 'active']);
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
            'email' => 'foreign@other.test',
        ]);

        app(UserInvitationService::class)->createAndSend(
            company: $otherCompany,
            employee: $otherEmployee,
            invitedByType: 'manager',
            invitedByEmail: 'boss@other.test',
        );

        $response = $this->actingAs($this->principal, 'web')->get('/hr/invitations');

        $response->assertOk();
        $response->assertSee('mine@company.test');
        $response->assertDontSee('foreign@other.test');
    }

    // ---------------------------------------------------------------------
    // POST /hr/invitations/{invitation}/resend
    // ---------------------------------------------------------------------

    public function test_resending_an_accepted_invitation_from_the_web_is_refused(): void
    {
        [$invitation] = $this->inviteEmployee(['email' => 'web.accepted@company.test']);

        $invitation->accepted_at = now();
        $invitation->save();
        $hashBefore = $invitation->token_hash;

        $response = $this->actingAs($this->principal, 'web')
            ->from('/hr/invitations')
            ->post("/hr/invitations/{$invitation->id}/resend");

        $response->assertRedirect('/hr/invitations');
        $response->assertSessionHasErrors('invitation');

        $invitation->refresh();
        $this->assertSame($hashBefore, $invitation->token_hash, 'Un refus ne doit pas rotationner le jeton.');
    }

    public function test_resending_a_pending_invitation_from_the_web_rotates_its_token(): void
    {
        [$invitation] = $this->inviteEmployee(['email' => 'web.pending@company.test']);

        $hashBefore = $invitation->token_hash;

        $response = $this->actingAs($this->principal, 'web')
            ->from('/hr/invitations')
            ->post("/hr/invitations/{$invitation->id}/resend");

        $response->assertRedirect('/hr/invitations');
        $response->assertSessionHasNoErrors();

        $invitation->refresh();
        $this->assertNotSame($hashBefore, $invitation->token_hash, 'Le renvoi doit rotationner le jeton.');
        $this->assertNull($invitation->accepted_at);
    }

    /**
     * Isolation inter-tenant sur le geste d'ecriture : un manager ne peut pas
     * renvoyer l'invitation d'une autre entreprise, meme en connaissant son id.
     */
    public function test_resending_an_invitation_of_another_company_is_not_found(): void
    {
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
            'email' => 'foreign.resend@other.test',
        ]);

        app(UserInvitationService::class)->createAndSend(
            company: $otherCompany,
            employee: $otherEmployee,
            invitedByType: 'manager',
            invitedByEmail: 'boss@other.test',
        );

        $foreign = UserInvitation::query()
            ->where('company_id', $otherCompany->id)
            ->where('employee_id', $otherEmployee->id)
            ->firstOrFail();
        $hashBefore = $foreign->token_hash;

        $this->actingAs($this->principal, 'web')
            ->from('/hr/invitations')
            ->post("/hr/invitations/{$foreign->id}/resend")
            ->assertNotFound();

        $foreign->refresh();
        $this->assertSame($hashBefore, $foreign->token_hash, 'Une invitation etrangere ne doit pas etre touchee.');
    }

    /**
     * #9322 cote web : quand le transport mail est indisponible, le controleur
     * doit relayer le lien d'activation au manager au lieu d'annoncer un envoi
     * qui n'a pas eu lieu.
     */
    public function test_the_web_resend_relays_the_link_when_the_mailer_is_down(): void
    {
        config()->set('app.frontend_url', 'https://app.leopardo.test');
        config()->set('mail.default', '');

        [$invitation] = $this->inviteEmployee(['email' => 'web.maildown@company.test']);

        $response = $this->actingAs($this->principal, 'web')
            ->from('/hr/invitations')
            ->post("/hr/invitations/{$invitation->id}/resend");

        $response->assertRedirect('/hr/invitations');
        $response->assertSessionHasNoErrors();

        $relayed = session('invitation_activation_url');
        $this->assertIsString($relayed);
        $this->assertStringStartsWith('https://app.leopardo.test/activate/', $relayed);

        // Le lien relaye doit correspondre au jeton REELLEMENT persiste.
        $invitation->refresh();
        $token = substr($relayed, strlen('https://app.leopardo.test/activate/'));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
    }
}
