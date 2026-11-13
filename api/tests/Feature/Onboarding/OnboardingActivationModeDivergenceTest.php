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
 * Divergence CONSTATÉE entre les deux modes d'activation, sur un employé
 * suspendu. Ce fichier ne valide pas le comportement comme souhaitable : il le
 * fige pour qu'il ne dérive pas silencieusement, et documente l'écart.
 *
 * Mode LIEN (`POST /onboarding/invitation/{token}/activate`)
 *   201 ACCOUNT_ACTIVATED, `data.token = null` — l'activation est acquise et
 *   l'échec de connexion automatique est absorbé (dégradation volontaire).
 *
 * Mode INSCRIPTION (`POST /auth/register`)
 *   403 EMPLOYEE_NOT_ACTIVE — ET, malgré ce code d'erreur, l'activation a
 *   EU LIEU : `accept()` a déjà consommé l'invitation (`accepted_at`), posé le
 *   `password_hash` et `invitation_accepted_at`, puis `login()` a levé AVANT
 *   que l'employé puisse être renvoyé.
 *
 * Conséquence pour un client : le formulaire d'inscription affiche un échec
 * alors que le compte est activé et que le lien est consommé — l'utilisateur
 * ne peut plus réessayer, et doit passer par « mot de passe oublié ».
 *
 * Ce comportement est ANTÉRIEUR à ce lot. Il n'est pas corrigé ici parce que
 * le corriger change un contrat HTTP public (`/auth/register`) et la
 * sémantique d'activation — décision produit hors périmètre de la mission
 * « correction de l'onboarding ». Le test sert de garde : si le comportement
 * change, il casse et force la mise à jour du contrat ET du client.
 *
 * Voir RAPPORT_CORRECTION_ONBOARDING.md, point ouvert « divergence 403 vs
 * dégradation sur employé suspendu ».
 */
class OnboardingActivationModeDivergenceTest extends TestCase
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
     * @return array{0: UserInvitation, 1: Employee, 2: string}
     */
    private function inviteSuspendedEmployee(string $email): array
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => $email,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $token = app(UserInvitationService::class)->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->manager->email,
        )->token;

        // Suspension APRÈS émission : le lien reste techniquement valide.
        $employee->forceFill(['status' => 'suspended'])->save();

        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return [$invitation, $employee, $token];
    }

    /** @test */
    public function registration_mode_returns_403_but_still_consumes_the_invitation(): void
    {
        [$invitation, $employee, $token] = $this->inviteSuspendedEmployee('divergence.register@company.test');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Divergence',
            'last_name' => 'Register',
            'email' => 'divergence.register@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(403)
            ->assertJsonPath('error', 'EMPLOYEE_NOT_ACTIVE');

        // Écart documenté : l'erreur est renvoyée APRÈS l'écriture.
        $employee->refresh();
        $invitation->refresh();

        $this->assertNotNull(
            $invitation->accepted_at,
            'Constat : le mode inscription consomme l\'invitation malgré le 403.',
        );
        $this->assertNotNull($employee->invitation_accepted_at);
        $this->assertNotNull($employee->password_hash);

        // Et la suspension, elle, n'a pas été levée.
        $this->assertSame('suspended', $employee->status);
    }

    /** @test */
    public function le_mode_lien_degrade_au_lieu_d_echouer_sur_le_meme_etat(): void
    {
        [$invitation, $employee, $token] = $this->inviteSuspendedEmployee('divergence.link@company.test');

        // MÊME état de départ, autre surface : ici l'échec de connexion est
        // absorbé et l'appelant reçoit un 201 explicite.
        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'ACCOUNT_ACTIVATED')
            ->assertJsonPath('data.token', null);

        $employee->refresh();
        $invitation->refresh();

        $this->assertSame('suspended', $employee->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    /** @test */
    public function les_deux_modes_consomment_l_invitation_malgre_un_contrat_http_different(): void
    {
        // Points communs aux deux modes : invitation consommée, mot de passe
        // posé, suspension préservée. Seul le code HTTP diffère (403 vs 201).
        [$invitationA, $employeeA, $tokenA] = $this->inviteSuspendedEmployee('parite.a@company.test');
        [$invitationB, $employeeB, $tokenB] = $this->inviteSuspendedEmployee('parite.b@company.test');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'A',
            'last_name' => 'Mode',
            'email' => 'parite.a@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $tokenA,
        ])->assertStatus(403);

        $this->postJson("/api/v1/onboarding/invitation/{$tokenB}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        foreach ([[$invitationA, $employeeA], [$invitationB, $employeeB]] as [$invitation, $employee]) {
            $invitation->refresh();
            $employee->refresh();

            $this->assertNotNull($invitation->accepted_at);
            $this->assertNotNull($employee->invitation_accepted_at);
            $this->assertNotNull($employee->password_hash);
            $this->assertSame('suspended', $employee->status);

            // Le mot de passe posé est le même dans les deux modes : la
            // connexion manuelle après réactivation fonctionnera pareil.
            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check('MotDePasseSolide123!', (string) $employee->password_hash),
            );
        }
    }
}
