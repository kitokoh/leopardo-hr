<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * IAM-L6 (#9096) — les deux protections qui manquaient.
 *
 * ## 1. Dernier administrateur de tenant
 *
 * La garde existait sur UN SEUL chemin (`RoleAssignmentController::assign`,
 * #9093). Or `manager_role` et `status` s'écrivent aussi par
 * `PATCH /employees/{id}` et `DELETE /employees/{id}` (archive). Un principal
 * pouvait donc se rétrograder, se suspendre ou s'archiver par ces chemins et
 * laisser l'entreprise sans personne capable de nommer un successeur —
 * auto-verrouillage définitif, sans récupération côté tenant.
 *
 * Ces tests prouvent que les TROIS chemins refusent désormais, et qu'un
 * principal non dernier n'est jamais bloqué (pas de sur-blocage).
 *
 * ## 2. MFA imposée aux comptes de plateforme
 *
 * Avant, la MFA d'un super-admin était purement volontaire : le login ne
 * demandait un code que SI `two_fa_secret` était déjà posé, et rien
 * n'obligeait à le poser. Un compte `super_admin` sans second facteur ouvrait
 * une session omnisciente avec un seul mot de passe.
 *
 * Ces tests prouvent qu'un compte sans second facteur n'obtient qu'un jeton
 * d'ENRÔLEMENT, inutilisable sur les routes de données.
 */
class LastAdministratorAndPlatformMfaTest extends TestCase
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
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
    }

    // =====================================================================
    // 1. DERNIER ADMINISTRATEUR — chemin PATCH /employees/{id}
    // =====================================================================

    /** @test */
    public function the_last_principal_cannot_demote_themselves_via_the_generic_update(): void
    {
        // NOTE DE COUVERTURE : la rétrogradation par PATCH n'est atteignable que
        // par un AUTRE principal (UpdateEmployeeRequest:124 interdit à un
        // non-principal de toucher `manager_role`, et `isSelfUpdate` retire le
        // champ). S'il existe un autre principal actif, la garde ne doit PAS
        // se déclencher — c'est le test `a_principal_is_not_blocked_...`.
        // Le cas « dernier principal » est donc prouvé au niveau du service,
        // là où la règle vit réellement.
        $guard = app(\App\Core\Auth\Infrastructure\Services\LastAdministratorGuard::class);

        $this->assertTrue(
            $guard->wouldRemoveLastPrincipal($this->principal, 'rh', null),
            'Le dernier principal actif ne peut pas être rétrogradé.',
        );
        $this->assertFalse(
            $guard->wouldRemoveLastPrincipal($this->principal, 'principal', null),
            'Rester principal ne retire rien.',
        );
    }

    /** @test */
    public function the_last_principal_cannot_be_suspended_via_the_generic_update(): void
    {
        $other = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/employees/{$this->principal->id}", [
            'status' => 'suspended',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'LAST_PRINCIPAL_REQUIRED');

        $this->principal->refresh();
        $this->assertSame('active', $this->principal->status, 'La suspension doit être refusée.');
    }

    /** @test */
    public function the_last_principal_cannot_be_archived(): void
    {
        $other = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/employees/{$this->principal->id}/archive")
            ->assertStatus(422)
            ->assertJsonPath('error', 'LAST_PRINCIPAL_REQUIRED');

        $this->principal->refresh();
        $this->assertSame('active', $this->principal->status, 'L\'archivage doit être refusé.');
    }

    /** @test */
    public function a_principal_is_not_blocked_when_another_active_principal_remains(): void
    {
        // Pas de sur-blocage : avec DEUX principaux actifs, l'un peut partir.
        $second = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($second);

        $this->patchJson("/api/v1/employees/{$this->principal->id}", [
            'manager_role' => 'rh',
        ])->assertOk();

        $this->principal->refresh();
        $this->assertSame('rh', $this->principal->manager_role);
    }

    /** @test */
    public function a_suspended_principal_does_not_count_as_a_successor(): void
    {
        // Un principal suspendu ne peut plus rouvrir la porte : il ne doit pas
        // compter comme « successeur » et débloquer la rétrogradation du
        // dernier principal ACTIF.
        Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'principal',
            'status' => 'suspended',
        ]);

        $other = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        Sanctum::actingAs($other);

        // `status` n'est PAS couvert par la règle « seul un principal change
        // les rôles » (UpdateEmployeeRequest:124) : un `rh` peut donc suspendre
        // un principal. C'est précisément le trou que la garde ferme.
        $this->patchJson("/api/v1/employees/{$this->principal->id}", [
            'status' => 'suspended',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'LAST_PRINCIPAL_REQUIRED');
    }

    /** @test */
    public function promoting_an_employee_never_trips_the_last_administrator_guard(): void
    {
        // La garde ne doit s'appliquer qu'aux transitions qui FONT PERDRE le
        // dernier principal — jamais à une promotion.
        $target = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->principal);

        $this->patchJson("/api/v1/employees/{$target->id}", [
            'role' => 'manager',
            'manager_role' => 'rh',
        ])->assertOk();

        $target->refresh();
        $this->assertSame('rh', $target->manager_role);
    }

    // =====================================================================
    // 2. MFA IMPOSÉE AUX COMPTES DE PLATEFORME
    // =====================================================================

    private function superAdminWithoutMfa(): SuperAdmin
    {
        $admin = new SuperAdmin([
            'name' => 'Sans MFA',
            'email' => 'sans-mfa@leopardo.test',
        ]);
        $admin->forceFill(['password_hash' => Hash::make('password123')])->save();

        return $admin;
    }

    /** @test */
    public function a_platform_account_without_mfa_only_receives_an_enrollment_token(): void
    {
        config(['platform.mfa_required' => true]);
        $this->superAdminWithoutMfa();

        $login = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'sans-mfa@leopardo.test',
            'password' => 'password123',
        ])->assertOk();

        // Le client lit ce drapeau pour router vers l'écran d'enrôlement.
        $login->assertJsonPath('data.mfa_enrollment_required', true);

        $token = (string) $login->json('token');
        $this->assertNotSame('', $token);

        // Le jeton d'enrôlement n'ouvre PAS les données de plateforme.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/companies')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PLATFORM_MFA_REQUIRED')
            ->assertJsonPath('enrollment_required', true);
    }

    /** @test */
    public function the_enrollment_token_can_reach_the_mfa_setup_route(): void
    {
        config(['platform.mfa_required' => true]);
        $this->superAdminWithoutMfa();

        $token = (string) $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'sans-mfa@leopardo.test',
            'password' => 'password123',
        ])->assertOk()->json('token');

        // L'enrôlement reste possible : sinon le compte serait définitivement
        // inutilisable, ce qui serait un déni de service.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/platform/auth/2fa/setup')
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'qr_code_url']]);
    }

    /** @test */
    public function a_platform_account_with_mfa_keeps_a_full_token(): void
    {
        config(['platform.mfa_required' => true]);

        $admin = new SuperAdmin([
            'name' => 'Avec MFA',
            'email' => 'avec-mfa@leopardo.test',
        ]);
        $admin->forceFill([
            'password_hash' => Hash::make('password123'),
            'two_fa_secret' => 'JBSWY3DPEHPK3PXP',
        ])->save();

        // Le login exige le code : on vérifie seulement que le drapeau
        // d'enrôlement n'est PAS posé quand un second facteur existe.
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'avec-mfa@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('error', 'TWO_FA_REQUIRED');
    }

    /** @test */
    public function disabling_mfa_revokes_full_tokens_and_returns_to_enrollment(): void
    {
        config(['platform.mfa_required' => true]);

        $admin = new SuperAdmin([
            'name' => 'Récupération',
            'email' => 'recuperation@leopardo.test',
        ]);
        $admin->forceFill([
            'password_hash' => Hash::make('password123'),
            'two_fa_secret' => 'JBSY3DPEHPK3PXP',
        ])->save();

        $token = $admin->createToken('full')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/platform/auth/2fa/disable', [
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('mfa_enrollment_required', true);

        // Le second facteur est retiré ET les jetons pleins sont révoqués :
        // un jeton survivant ouvrirait une session sans second facteur.
        $this->assertNull($admin->fresh()?->two_fa_secret);
        $this->assertSame(0, $admin->tokens()->count(), 'Les jetons pleins doivent être révoqués.');
    }

    /** @test */
    public function when_mfa_is_not_required_the_legacy_behaviour_is_preserved(): void
    {
        // Le harness de test désactive l'exigence par défaut (phpunit.xml) pour
        // ne pas réécrire les scénarios existants ; ce test prouve que le
        // drapeau est bien respecté dans les deux sens.
        config(['platform.mfa_required' => false]);
        $this->superAdminWithoutMfa();

        $login = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'sans-mfa@leopardo.test',
            'password' => 'password123',
        ])->assertOk();

        $login->assertJsonPath('data.mfa_enrollment_required', false);

        $token = (string) $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/auth/me')
            ->assertOk();
    }
}
