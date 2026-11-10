<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #9322 — le lien d'activation collaborateur doit être exposé au manager
 * quand le transport mail n'a pas pu délivrer l'invitation.
 *
 * Contexte : #9305/#9306 (PR #9310) ont garanti qu'un RESPONSABLE qui s'inscrit
 * obtient toujours un espace ET un chemin de connexion, mailer KO. Le parcours
 * COLLABORATEUR portait la même faille un cran plus loin : `createAndSend()`
 * persistait l'invitation et avalait l'échec d'envoi (#1776), mais le jeton en
 * clair n'était exposé nulle part — invitation valide en base, intransmissible.
 *
 * Contrat vérifié ici :
 * - envoi réussi  ⇒ jamais de lien exposé (le jeton clair n'est pas un canal
 *   de reprise de compte, cf. #9114/#9117) ;
 * - envoi échoué  ⇒ lien exposé, sur la création ET sur le renvoi ;
 * - invitation déjà consommée ⇒ ni jeton, ni lien.
 */
class InvitationActivationLinkExposureTest extends TestCase
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

        config()->set('app.frontend_url', 'https://app.leopardo.test');
    }

    /**
     * Transport mail absent : reproduit l'état de la production (#9318).
     * `mail.default` vide fait lever « Unsupported mail transport [] ».
     */
    private function breakMailer(): void
    {
        config()->set('mail.default', '');
    }

    public function test_creation_exposes_activation_url_when_mail_fails(): void
    {
        $this->breakMailer();
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Nadia',
            'last_name' => 'K',
            'email' => 'nadia@example.dz',
            'role' => 'employee',
            'send_invitation' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invitation_mail_sent', false);

        $activationUrl = $response->json('data.invitation_activation_url');

        $this->assertIsString($activationUrl);
        $this->assertNotSame('', $activationUrl);
        $this->assertStringStartsWith('https://app.leopardo.test/activate/', $activationUrl);

        // Le collaborateur existe, l'invitation aussi : le lien DOIT correspondre
        // au jeton réellement persisté (sinon le manager relaie un lien mort).
        $token = substr($activationUrl, strlen('https://app.leopardo.test/activate/'));

        $this->assertDatabaseHas('user_invitations', [
            'email' => 'nadia@example.dz',
            'company_id' => $this->company->id,
            'token_hash' => hash('sha256', $token),
        ]);

        /** @var UserInvitation $invitation */
        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('email', 'nadia@example.dz')
            ->firstOrFail();

        // L'échec est tracé, l'invitation reste ouverte (pas consommée).
        $this->assertNull($invitation->accepted_at);
        $this->assertSame(false, $invitation->metadata['last_mail_delivered'] ?? null);
        $this->assertNotNull($invitation->expires_at);
    }

    public function test_creation_never_exposes_activation_url_when_mail_succeeds(): void
    {
        // Aucun transport cassé : le tableau mailer de test accepte l'envoi.
        config()->set('mail.default', 'array');

        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Samir',
            'last_name' => 'B',
            'email' => 'samir@example.dz',
            'role' => 'employee',
            'send_invitation' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invitation_mail_sent', true);

        // Garde-fou #9114/#9117 : un envoi réussi ne divulgue JAMAIS le jeton.
        $this->assertNull($response->json('data.invitation_activation_url'));
    }

    public function test_resend_exposes_activation_url_when_mail_fails(): void
    {
        $this->breakMailer();
        Sanctum::actingAs($this->manager);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'resend.link@example.dz',
        ]);

        $invitation = new UserInvitation;
        $invitation->forceFill([
            'employee_id' => $employee->id,
            'email' => $employee->email,
            'schema_name' => $this->company->schema_name,
            'invited_by_type' => 'manager',
            'invited_by_email' => $this->manager->email,
            'token_hash' => hash('sha256', 'stale-token'),
            'expires_at' => now()->addDays(7),
            'company_id' => $this->company->id,
            'role' => $employee->role,
        ]);
        $invitation->save();

        $response = $this->postJson("/api/v1/invitations/{$invitation->id}/resend");

        $response->assertOk()
            ->assertJsonPath('data.email', 'resend.link@example.dz')
            ->assertJsonPath('data.mail_sent', false);

        $activationUrl = $response->json('data.activation_url');
        $this->assertIsString($activationUrl);
        $this->assertNotSame('', $activationUrl);

        // Le renvoi fait tourner le jeton : le lien exposé doit être le NOUVEAU.
        $token = substr($activationUrl, strlen('https://app.leopardo.test/activate/'));

        $invitation->refresh();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame(hash('sha256', 'stale-token'), $invitation->token_hash);
    }

    public function test_resend_reports_mail_sent_and_hides_link_on_success(): void
    {
        config()->set('mail.default', 'array');
        Sanctum::actingAs($this->manager);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'resend.ok@example.dz',
        ]);

        $invitation = new UserInvitation;
        $invitation->forceFill([
            'employee_id' => $employee->id,
            'email' => $employee->email,
            'schema_name' => $this->company->schema_name,
            'invited_by_type' => 'manager',
            'invited_by_email' => $this->manager->email,
            'token_hash' => hash('sha256', 'stale-token'),
            'expires_at' => now()->addDays(7),
            'company_id' => $this->company->id,
            'role' => $employee->role,
        ]);
        $invitation->save();

        $response = $this->postJson("/api/v1/invitations/{$invitation->id}/resend");

        $response->assertOk()->assertJsonPath('data.mail_sent', true);
        $this->assertNull($response->json('data.activation_url'));
    }

    public function test_dispatch_result_for_consumed_invitation_exposes_nothing(): void
    {
        config()->set('mail.default', 'array');

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'deja.actif.link@example.dz',
        ]);

        /** @var UserInvitationService $service */
        $service = app(UserInvitationService::class);

        $service->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->manager->email,
        );

        /** @var UserInvitation $invitation */
        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $invitation->accepted_at = now();
        $invitation->save();

        $result = $service->createAndSend(
            company: $this->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $this->manager->email,
        );

        $this->assertSame('', $result->token);
        $this->assertFalse($result->mailSent);
        $this->assertFalse($result->hasFallbackLink());
        $this->assertNull($result->activationUrl);
    }

    public function test_invitation_index_reports_mail_delivery_outcome(): void
    {
        $this->breakMailer();
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Lila',
            'last_name' => 'M',
            'email' => 'lila@example.dz',
            'role' => 'employee',
            'send_invitation' => true,
        ])->assertCreated();

        $this->getJson('/api/v1/invitations')
            ->assertOk()
            // #9322 : l'UI doit savoir que le mail n'est pas parti, sinon elle
            // affiche un « envoyer » trompeur et le manager ne relaie rien.
            ->assertJsonPath('data.0.mail_delivered', false);
    }
}
