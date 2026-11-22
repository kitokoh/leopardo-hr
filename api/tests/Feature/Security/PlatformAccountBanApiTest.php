<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TokenRevocationService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #9815 — le bannissement de compte est exposé côté API plateforme.
 *
 * ## Ce que ce test verrouille
 *
 * #9808 avait livré `TokenRevocationService` et la commande `auth:ban-account`,
 * mais aucun chemin HTTP : la console plateforme ne pouvait pas couper l'accès
 * d'un compte compromis. Le docblock de `BanAccountCommand` annonçait même un
 * `PlatformAccountBanController` qui n'existait pas.
 *
 * Contrat vérifié ici, sur les VRAIES routes (pas sur le service) :
 *
 *  1. `POST` bannit temporairement (`hours`) — le jeton du compte est refusé ;
 *  2. `POST` sans `hours` bannit définitivement ;
 *  3. `DELETE` lève le bannissement — l'accès revient ;
 *  4. `GET` consulte l'état sans révéler le compte ;
 *  5. un type inconnu est refusé (422) **sans écrire de clé fantôme** ;
 *  6. un rôle sans `accounts.ban` est refusé (403) — la permission est
 *     distincte de `users.manage`.
 *
 * Le point 5 est le plus important : une clé `auth:banned:typo:42` ne serait
 * jamais relue par `AccountStatusGuard`, donc le bannissement serait
 * silencieusement sans effet.
 */
class PlatformAccountBanApiTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $employee;

    private TokenRevocationService $revocations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // Store en mémoire : le test ne dépend d'aucun Redis.
        config(['cache.default' => 'array']);
        config(['auth-revocation.enabled' => true]);
        config(['auth-revocation.store' => null]);
        // Le harness de test désactive la MFA plateforme (phpunit.xml) ; on le
        // réaffirme ici pour que le test ne dépende pas de l'ordre d'exécution.
        config(['platform.mfa_required' => false]);

        $this->company = Company::query()->create([
            'name' => 'Société Bannissement',
            'slug' => 'societe-bannissement',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@bannissement.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->revocations = app(TokenRevocationService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── 1. Bannissement temporaire ───────────────────────────────────────────

    public function test_post_with_hours_bans_temporarily_and_the_token_is_refused(): void
    {
        $this->actingAsPlatformAdmin();

        $token = $this->employee->createToken('test')->accessToken;

        $response = $this->postJson($this->banUrl(), [
            'hours' => 24,
            'reason' => 'abus signalé',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.banned', true)
            ->assertJsonPath('data.account_type', TokenRevocationService::TYPE_EMPLOYEE)
            ->assertJsonPath('data.account_id', (int) $this->employee->id);

        // Le vrai contrat : le service porte la décision, pas la réponse HTTP.
        $this->assertTrue($this->revocations->isBanned(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
        ));

        // Et le jeton du compte est effectivement refusé.
        $this->assertFalse(app(\App\Core\Auth\Infrastructure\Services\AccountStatusGuard::class)
            ->tokenIsUsable($token));
    }

    // ── 2. Bannissement permanent ────────────────────────────────────────────

    public function test_post_without_hours_bans_permanently(): void
    {
        $this->actingAsPlatformAdmin();

        $this->postJson($this->banUrl(), ['reason' => 'compromission'])
            ->assertCreated()
            ->assertJsonPath('data.banned', true);

        $this->assertTrue($this->revocations->isBanned(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
        ));
    }

    // ── 3. Levée ─────────────────────────────────────────────────────────────

    public function test_delete_lifts_the_ban_and_access_returns(): void
    {
        $this->actingAsPlatformAdmin();

        $token = $this->employee->createToken('test')->accessToken;

        $this->postJson($this->banUrl(), [])->assertCreated();
        $this->assertFalse(app(\App\Core\Auth\Infrastructure\Services\AccountStatusGuard::class)
            ->tokenIsUsable($token));

        $this->deleteJson($this->banUrl())
            ->assertOk()
            ->assertJsonPath('data.banned', false);

        $this->assertFalse($this->revocations->isBanned(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
        ));

        $this->assertTrue(app(\App\Core\Auth\Infrastructure\Services\AccountStatusGuard::class)
            ->tokenIsUsable($token));
    }

    public function test_delete_is_idempotent_when_no_ban_exists(): void
    {
        $this->actingAsPlatformAdmin();

        // Lever un bannissement absent réussit : un 404 obligerait l'appelant
        // à interroger l'état avant un geste déjà sans effet.
        $this->deleteJson($this->banUrl())
            ->assertOk()
            ->assertJsonPath('data.banned', false);
    }

    // ── 4. Consultation ──────────────────────────────────────────────────────

    public function test_get_reports_the_state_without_leaking_the_account(): void
    {
        $this->actingAsPlatformAdmin();

        $this->getJson($this->banUrl())
            ->assertOk()
            ->assertJsonPath('data.banned', false)
            ->assertJsonPath('data.account_type', TokenRevocationService::TYPE_EMPLOYEE)
            ->assertJsonPath('data.account_id', (int) $this->employee->id)
            // Aucune donnée personnelle : ni nom, ni e-mail.
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.email');

        $this->postJson($this->banUrl(), [])->assertCreated();

        $this->getJson($this->banUrl())
            ->assertOk()
            ->assertJsonPath('data.banned', true);
    }

    // ── 5. Type inconnu : fail-closed, aucune clé fantôme ────────────────────

    public function test_an_unknown_account_type_is_refused_without_writing_a_key(): void
    {
        $this->actingAsPlatformAdmin();

        $this->postJson('/api/v1/platform/accounts/typo/'.$this->employee->id.'/ban', [])
            ->assertStatus(422);

        // La clé fantôme ne doit PAS exister : elle ne serait jamais relue par
        // `AccountStatusGuard`, donc le bannissement serait sans effet.
        $this->assertFalse($this->revocations->isBanned('typo', (int) $this->employee->id));
        $this->assertFalse($this->revocations->isBanned(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
        ));
    }

    public function test_a_non_numeric_account_id_is_refused(): void
    {
        $this->actingAsPlatformAdmin();

        $this->postJson('/api/v1/platform/accounts/employee/abc/ban', [])
            ->assertStatus(404);
    }

    // ── 6. Permission dédiée ─────────────────────────────────────────────────

    public function test_a_role_without_accounts_ban_is_refused(): void
    {
        // `finance` ne porte pas `accounts.ban` : il n'a aucune raison de
        // couper un accès. C'est la preuve que la permission est distincte de
        // `users.manage` (que finance ne porte pas non plus) et qu'elle est
        // réellement vérifiée.
        $this->actingAsPlatformAdmin('finance');

        $this->postJson($this->banUrl(), [])->assertStatus(403);

        $this->assertFalse($this->revocations->isBanned(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
        ));
    }

    public function test_support_and_ops_can_ban(): void
    {
        foreach (['support', 'ops'] as $role) {
            $this->actingAsPlatformAdmin($role);

            $this->postJson($this->banUrl(), [])->assertCreated();

            $this->deleteJson($this->banUrl())->assertOk();
        }
    }

    // ── Aides ────────────────────────────────────────────────────────────────

    private function banUrl(): string
    {
        return '/api/v1/platform/accounts/'
            .TokenRevocationService::TYPE_EMPLOYEE.'/'
            .$this->employee->id.'/ban';
    }

    private function actingAsPlatformAdmin(string $role = 'super_admin'): void
    {
        $admin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'ban-'.$role.'@leopardo.test',
        ]);
        $admin->forceFill([
            'password_hash' => Hash::make('password123'),
            'platform_role' => $role,
        ])->save();

        Sanctum::actingAs($admin, ['*'], 'super_admin_api');
    }
}
