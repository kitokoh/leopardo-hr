<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Trous de couverture restants sur les TROIS populations d'onboarding.
 *
 * Les rounds précédents ont couvert le parcours nominal, la convergence des
 * deux chemins d'activation, les états du cycle de vie et la gestion des
 * invitations. Ce fichier verrouille les invariants qui restaient sans test :
 *
 *  - **grand public** : le rattachement des réservations à l'inscription ne
 *    Vole jamais une réservation déjà possédée par un autre compte, et ne
 *    revendique QUE les réservations issues de la marketplace (jamais une
 *    réservation de guichet portant le même e-mail) ;
 *  - **employé invité** : `/auth/register` refuse une invitation déjà
 *    consommée et une invitation expirée avec le contrat historique
 *    `422 REGISTRATION_NOT_AVAILABLE` (aucun oracle sur l'état exact) ;
 *  - **employé invité** : le pré-contrôle de `/auth/register` ne teste pas le
 *    statut de l'employé — un employé archivé franchit le pré-contrôle puis
 *    `accept()` lève `410 INVITATION_REVOKED`. Ce test FIGE ce comportement
 *    réel (voir le rapport, point ouvert « oracle d'état »).
 *
 * Aucun code applicatif n'est modifié : ces tests décrivent le comportement
 * observé pour qu'une régression future casse la suite.
 */
class OnboardingPopulationCoverageGapsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'voyage-secret-2026';

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

    /**
     * Crée une réservation directement en base, dans le contexte du tenant.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function booking(array $attributes): TravelBooking
    {
        return app(TenantManager::class)->withinTenant(
            $this->company,
            fn (): TravelBooking => TravelBooking::factory()->create(array_merge([
                'booking_source' => BookingSource::MARKETPLACE->value,
                'contact_email' => 'client@example.test',
            ], $attributes)),
        );
    }

    // ---------------------------------------------------------------------
    // Population 3 — grand public : bornage du rattachement
    // ---------------------------------------------------------------------

    /**
     * @test
     */
    public function registration_never_steals_a_booking_already_owned_by_another_account(): void
    {
        // Une réservation déjà rattachée à un AUTRE compte, mais dont l'e-mail
        // de contact est celui du nouvel inscrit (cas réel : un proche réserve
        // pour quelqu'un d'autre, ou l'e-mail a été saisi par un tiers).
        $owner = TravelCustomerAccount::query()->create([
            'name' => 'Premier Compte',
            'email' => 'premier@example.test',
            'password' => bcrypt(self::PASSWORD),
        ]);

        $owned = $this->booking([
            'contact_email' => 'nouveau@example.test',
            'customer_account_id' => $owner->id,
        ]);

        $data = $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => 'Nouveau Compte',
            'email' => 'nouveau@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(201)->json('data');

        // Aucune réservation revendiquée : `whereNull('customer_account_id')`
        // protège la propriété existante.
        $this->assertSame(0, (int) $data['claimed_bookings']);

        $owned->refresh();
        $this->assertSame(
            (int) $owner->id,
            (int) $owned->getAttribute('customer_account_id'),
            'Une réservation déjà possédée ne doit jamais changer de propriétaire.',
        );
    }

    /**
     * @test
     */
    public function registration_only_claims_marketplace_bookings(): void
    {
        // Même e-mail, mais réservation de GUICHET : elle n'appartient pas à la
        // surface marketplace et ne doit pas être revendiquée par un compte
        // grand public.
        $office = $this->booking([
            'contact_email' => 'guichet@example.test',
            'booking_source' => BookingSource::OFFICE->value,
        ]);

        $marketplace = $this->booking([
            'contact_email' => 'guichet@example.test',
            'booking_source' => BookingSource::MARKETPLACE->value,
        ]);

        $data = $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => 'Client Guichet',
            'email' => 'guichet@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(201)->json('data');

        $this->assertSame(1, (int) $data['claimed_bookings'], 'Seule la réservation marketplace est revendiquée.');

        $office->refresh();
        $marketplace->refresh();

        $this->assertNull(
            $office->getAttribute('customer_account_id'),
            'Une réservation de guichet ne doit jamais être rattachée à un compte grand public.',
        );
        $this->assertNotNull($marketplace->getAttribute('customer_account_id'));
    }

    // ---------------------------------------------------------------------
    // Population 2 — employé invité : contrat de /auth/register
    // ---------------------------------------------------------------------

    /**
     * @test
     */
    public function register_rejects_an_already_consumed_invitation(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'consommee@company.test']);

        // Première activation : succès.
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Premiere',
            'last_name' => 'Activation',
            'email' => 'consommee@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(201);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);

        // Seconde tentative avec le MÊME jeton : refus, contrat historique.
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Seconde',
            'last_name' => 'Tentative',
            'email' => 'consommee@company.test',
            'password' => 'AutreMotDePasse123!',
            'password_confirmation' => 'AutreMotDePasse123!',
            'invitation_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        // Le mot de passe d'origine n'a pas été écrasé.
        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('MotDePasseSolide123!', (string) $employee->password_hash),
            'Une invitation consommée ne doit jamais permettre de réécrire le mot de passe.',
        );
    }

    /**
     * @test
     */
    public function register_rejects_an_expired_invitation(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'expiree@company.test']);

        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Trop',
            'last_name' => 'Tard',
            'email' => 'expiree@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'REGISTRATION_NOT_AVAILABLE');

        $invitation->refresh();
        $this->assertNull($invitation->accepted_at, 'Une invitation expirée ne doit pas être consommée.');
    }

    /**
     * @test
     *
     * Comportement RÉEL figé (point ouvert du rapport) : le pré-contrôle de
     * `/auth/register` ne teste pas le statut de l'employé. Un employé archivé
     * franchit donc le pré-contrôle, puis `accept()` lève `410
     * INVITATION_REVOKED` — un code qui, sur une route publique, distingue
     * « invitation révoquée » de « invitation inconnue », alors que le contrat
     * documenté promet un 422 indifférencié.
     *
     * Ce test ne corrige rien : il rend la divergence visible et empêche
     * qu'elle change silencieusement.
     */
    public function register_on_an_archived_employee_leaks_the_revoked_state(): void
    {
        [$invitation, $token] = $this->inviteEmployee(['email' => 'archive@company.test']);

        $employee = Employee::query()->findOrFail($invitation->employee_id);
        $employee->forceFill(['status' => 'archived'])->save();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Archive',
            'last_name' => 'Test',
            'email' => 'archive@company.test',
            'password' => 'MotDePasseSolide123!',
            'password_confirmation' => 'MotDePasseSolide123!',
            'invitation_token' => $token,
        ]);

        // Comportement observé aujourd'hui : 410, pas 422.
        $response->assertStatus(410)
            ->assertJsonPath('error', 'INVITATION_REVOKED');

        // Aucune écriture : l'invitation reste ouverte, le mot de passe intact.
        $invitation->refresh();
        $this->assertNull($invitation->accepted_at);

        $employee->refresh();
        $this->assertSame('archived', $employee->status);
    }
}
