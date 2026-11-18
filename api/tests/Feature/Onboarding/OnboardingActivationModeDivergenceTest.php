<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Divergence de contrat entre les DEUX surfaces d'activation, sur un employé
 * dont le statut a changé APRÈS l'émission de l'invitation.
 *
 * ## Décision produit (round 13, #9096) — option B « refuser AVANT d'écrire »
 *
 * Les deux surfaces ne portent pas le même public et n'ont pas à partager la
 * même granularité d'erreur. Le contrat retenu est :
 *
 *  1. **Mode inscription (`POST /auth/register`)** — route PUBLIQUE : un
 *     employé non actif (suspendu, archivé, parti) est refusé par
 *     `422 REGISTRATION_NOT_AVAILABLE`, **indifférencié** (aucun oracle sur
 *     l'état de l'invitation ni sur le statut de l'employé), et **AVANT toute
 *     écriture** : l'invitation reste ouverte, le lien reste réutilisable une
 *     fois l'employé réactivé.
 *
 *  2. **Mode lien (`POST /onboarding/invitation/{token}/activate`)** — route
 *     destinée au porteur du lien : l'échec de connexion automatique est
 *     ABSORBÉ (`201` + `token: null`), le compte est activé et l'invitation
 *     consommée. Le porteur repasse par une connexion manuelle.
 *
 * Ce fichier FIGE ce contrat : si l'un des deux comportements change, le test
 * casse et force la mise à jour du contrat ET du client.
 *
 * Voir RAPPORT_CORRECTION_ONBOARDING.md, § « Contrat public /auth/register ».
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
     * @param  array<string, mixed>  $employeeAttributes
     * @return array{0: UserInvitation, 1: Employee, 2: string}
     */
    private function inviteEmployee(string $email, array $employeeAttributes = []): array
    {
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'email' => $email,
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

        return [$invitation, $employee, $token];
    }

    /**
     * @return array{0: UserInvitation, 1: Employee, 2: string}
     */
    private function inviteSuspendedEmployee(string $email): array
    {
        [$invitation, $employee, $token] = $this->inviteEmployee($email);

        // Suspension APRÈS émission : le lien reste techniquement valide.
        $employee->forceFill(['status' => 'suspended'])->save();

        return [$invitation, $employee, $token];
    }

    /** @test */
    public function register_refuses_a_suspended_employee_without_writing_anything(): void
    {
        [$invitation, $employee, $token] = $this->inviteSuspendedEmployee('divergence.register@company.test');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Divergence',
            'last_name' => 'Register',
            'email' => 'divergence.register@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        // Décision produit : le refus intervient AVANT l'écriture.
        $employee->refresh();
        $invitation->refresh();

        $this->assertNull(
            $invitation->accepted_at,
            'L\'invitation ne doit PAS être consommée : le lien reste réutilisable.',
        );
        $this->assertNull(
            $employee->invitation_accepted_at,
            'L\'activation ne doit pas avoir eu lieu.',
        );
        $this->assertSame('suspended', $employee->status, 'La suspension est préservée.');
    }

    /** @test */
    public function register_refuses_an_archived_employee_without_writing_and_without_leaking_state(): void
    {
        // Un employé `archived` produisait un 410 INVITATION_REVOKED sur une
        // route PUBLIQUE — un code qui distingue l'état exact de l'invitation
        // et permet de l'énumérer. Le contrat retenu est le 422 indifférencié.
        [$invitation, $employee, $token] = $this->inviteEmployee('divergence.archived@company.test');
        $employee->forceFill(['status' => 'archived'])->save();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Divergence',
            'last_name' => 'Archive',
            'email' => 'divergence.archived@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        $invitation->refresh();
        $employee->refresh();

        $this->assertNull($invitation->accepted_at);
        $this->assertSame('archived', $employee->status);
    }

    /** @test */
    public function register_refuses_a_departed_employee_with_the_undifferentiated_422(): void
    {
        [$invitation, $employee, $token] = $this->inviteEmployee('divergence.departed@company.test');
        $employee->forceFill(['status' => 'departed'])->save();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Divergence',
            'last_name' => 'Parti',
            'email' => 'divergence.departed@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        $invitation->refresh();

        $this->assertNull($invitation->accepted_at);
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
    public function le_mode_lien_garde_son_410_explicite_sur_un_employe_archive(): void
    {
        // Le mode lien n'a PAS changé : il s'adresse au porteur du lien, qui a
        // légitimement besoin de savoir que l'invitation est révoquée.
        [$invitation, $employee, $token] = $this->inviteEmployee('divergence.link.archived@company.test');
        $employee->forceFill(['status' => 'archived'])->save();

        $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_REVOKED');

        $invitation->refresh();

        $this->assertNull($invitation->accepted_at, 'L\'invitation révoquée n\'est pas consommée.');
    }

    /** @test */
    public function reactivating_the_employee_makes_the_same_link_usable_again(): void
    {
        // C'est le bénéfice concret de l'option B : le refus n'ayant rien écrit,
        // le MÊME lien fonctionne dès que l'employé est réactivé. Avec l'ancien
        // comportement (403 APRÈS écriture), l'invitation était déjà consommée
        // et l'utilisateur n'avait plus que « mot de passe oublié ».
        [$invitation, $employee, $token] = $this->inviteSuspendedEmployee('divergence.reactivable@company.test');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Re',
            'last_name' => 'Activé',
            'email' => 'divergence.reactivable@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(422);

        // L'employé est réactivé par son gestionnaire.
        $employee->forceFill(['status' => 'active'])->save();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Re',
            'last_name' => 'Activé',
            'email' => 'divergence.reactivable@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(201)
            ->assertJsonPath('data.email', 'divergence.reactivable@company.test');

        $invitation->refresh();
        $employee->refresh();

        $this->assertNotNull($invitation->accepted_at, 'Le lien réutilisé est bien consommé.');
        $this->assertTrue(
            Hash::check('MotDePasseSolide123!', (string) $employee->password_hash),
        );
    }

    /** @test */
    public function les_deux_modes_ont_un_contrat_different_sur_le_meme_etat(): void
    {
        // Synthèse de la décision : deux surfaces, deux publics, deux contrats.
        [, , $tokenRegister] = $this->inviteSuspendedEmployee('parite.a@company.test');
        [$invitationB, , $tokenLink] = $this->inviteSuspendedEmployee('parite.b@company.test');

        // Inscription : refus net, aucune écriture.
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'A',
            'last_name' => 'Mode',
            'email' => 'parite.a@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $tokenRegister,
        ])->assertStatus(422);

        // Lien : activation réussie avec dégradation explicite.
        $this->postJson("/api/v1/onboarding/invitation/{$tokenLink}/activate", [
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
        ])->assertStatus(201);

        $invitationB->refresh();

        $this->assertNotNull($invitationB->accepted_at, 'Le mode lien consomme bien l\'invitation.');
    }
}
