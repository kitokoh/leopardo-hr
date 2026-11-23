<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AccountStatusGuard;
use App\Core\Auth\Infrastructure\Services\TokenRevocationService;
use App\Core\Tenant\Domain\Models\Company;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issues #9808 / #9809 — révocation de jetons, bannissement et statut de compte.
 *
 * ## Ce que ce test verrouille
 *
 * Avant #9808, révoquer un jeton voulait dire une seule chose : supprimer sa
 * ligne. Ce test vérifie les trois propriétés que la suppression ne pouvait pas
 * offrir :
 *
 *  1. un jeton révoqué est refusé **sans que sa ligne soit supprimée** (donc la
 *     révocation est réversible et auditable) ;
 *  2. un bannissement de compte est **temporaire** (il expire tout seul) ;
 *  3. le TTL d'une clé de révocation ne dépasse **jamais** l'expiration réelle
 *     du jeton — pas de clé orpheline éternelle en cache.
 *
 * Et, pour #9809, la règle de statut : un compte `suspended` ou `archived` est
 * refusé, un compte `active` passe.
 */
class TokenRevocationTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $employee;

    private TokenRevocationService $revocations;

    private AccountStatusGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // Store en mémoire : le test ne doit dépendre d'aucun Redis.
        config(['cache.default' => 'array']);
        config(['auth-revocation.enabled' => true]);
        config(['auth-revocation.store' => null]);

        $this->company = Company::query()->create([
            'name' => 'Société Test',
            'slug' => 'societe-test',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@test.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->revocations = app(TokenRevocationService::class);
        $this->guard = app(AccountStatusGuard::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── 1. Révocation d'un jeton ────────────────────────────────────────────

    public function test_a_revoked_token_is_refused_without_deleting_its_row(): void
    {
        $token = $this->employee->createToken('test')->accessToken;
        $tokenId = (int) $token->id;

        $this->assertFalse($this->revocations->isRevoked($tokenId));
        $this->assertTrue($this->guard->tokenIsUsable($token));

        $this->revocations->revoke($tokenId, $token->expires_at?->toIso8601String(), 'test');

        $this->assertTrue($this->revocations->isRevoked($tokenId));
        $this->assertFalse($this->guard->tokenIsUsable($token));

        // La ligne existe TOUJOURS : c'est la propriété qui rend la révocation
        // réversible et auditable (une suppression ne revient pas).
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_revoking_an_already_expired_token_writes_nothing(): void
    {
        $token = $this->employee->createToken('test')->accessToken;
        $tokenId = (int) $token->id;

        $past = CarbonImmutable::now()->subHour()->toIso8601String();
        $this->revocations->revoke($tokenId, $past, 'test');

        // Rien à stocker : Sanctum refusera ce jeton de toute façon. On évite
        // une clé qui ne servirait jamais.
        $this->assertFalse($this->revocations->isRevoked($tokenId));
    }

    public function test_revocation_key_never_outlives_the_token(): void
    {
        $token = $this->employee->createToken('test')->accessToken;
        $tokenId = (int) $token->id;

        // Expiration dans 60 s : la clé doit disparaître avec le jeton.
        $soon = CarbonImmutable::now()->addSeconds(60)->toIso8601String();
        $this->revocations->revoke($tokenId, $soon, 'test');

        $this->assertTrue($this->revocations->isRevoked($tokenId));

        // On avance le temps au-delà de l'expiration : la clé a expiré.
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(120));
        $this->assertFalse($this->revocations->isRevoked($tokenId));
        CarbonImmutable::setTestNow();
    }

    // ── 2. Bannissement de compte ───────────────────────────────────────────

    public function test_a_banned_account_is_refused_even_with_a_valid_token(): void
    {
        $token = $this->employee->createToken('test')->accessToken;

        $this->assertTrue($this->guard->tokenIsUsable($token));

        $this->revocations->ban(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
            null,
            'abus',
        );

        $this->assertTrue($this->revocations->isBanned(TokenRevocationService::TYPE_EMPLOYEE, (int) $this->employee->id));
        $this->assertFalse($this->guard->tokenIsUsable($token));
    }

    public function test_a_temporary_ban_expires_on_its_own(): void
    {
        $token = $this->employee->createToken('test')->accessToken;

        $this->revocations->ban(
            TokenRevocationService::TYPE_EMPLOYEE,
            (int) $this->employee->id,
            CarbonImmutable::now()->addSeconds(60),
            'gel temporaire',
        );

        $this->assertFalse($this->guard->tokenIsUsable($token));

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(120));
        $this->assertTrue($this->guard->tokenIsUsable($token));
        CarbonImmutable::setTestNow();
    }

    public function test_unban_restores_access_immediately(): void
    {
        $token = $this->employee->createToken('test')->accessToken;

        $this->revocations->ban(TokenRevocationService::TYPE_EMPLOYEE, (int) $this->employee->id, null, 'erreur');
        $this->assertFalse($this->guard->tokenIsUsable($token));

        $this->revocations->unban(TokenRevocationService::TYPE_EMPLOYEE, (int) $this->employee->id);
        $this->assertTrue($this->guard->tokenIsUsable($token));
    }

    // ── 3. Statut de compte (#9809) ─────────────────────────────────────────

    public function test_a_suspended_account_is_refused(): void
    {
        $token = $this->employee->createToken('test')->accessToken;
        $this->assertTrue($this->guard->tokenIsUsable($token));

        $this->employee->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse($this->guard->tokenIsUsable($this->freshToken($token)));
    }

    public function test_an_archived_account_is_refused(): void
    {
        $token = $this->employee->createToken('test')->accessToken;

        $this->employee->forceFill(['status' => 'archived'])->save();

        $this->assertFalse($this->guard->tokenIsUsable($this->freshToken($token)));
    }

    public function test_an_active_account_passes(): void
    {
        $token = $this->employee->createToken('test')->accessToken;

        $this->employee->forceFill(['status' => 'active'])->save();

        $this->assertTrue($this->guard->tokenIsUsable($this->freshToken($token)));
    }

    /**
     * #9859 — `fresh()` est typé `PersonalAccessToken|null`, alors que
     * `tokenIsUsable()` exige un non-null. Le jeton existe forcément (on vient
     * de le créer) : on l'affirme au lieu de laisser PHPStan deviner, ce qui
     * documente aussi l'invariant du test.
     */
    private function freshToken(PersonalAccessToken $token): PersonalAccessToken
    {
        $fresh = $token->fresh();

        self::assertInstanceOf(PersonalAccessToken::class, $fresh);

        return $fresh;
    }

    // ── 4. Clés de cache ────────────────────────────────────────────────────

    public function test_cache_keys_are_prefixed_and_distinct(): void
    {
        $this->assertSame('leopardo:auth:revoked:token:42', $this->revocations->tokenKey(42));
        $this->assertSame('leopardo:auth:banned:employee:7', $this->revocations->banKey('employee', 7));
        $this->assertNotSame(
            $this->revocations->banKey('employee', 7),
            $this->revocations->banKey('user', 7),
        );
    }

    public function test_the_account_type_is_resolved_for_an_employee(): void
    {
        $this->assertSame(
            TokenRevocationService::TYPE_EMPLOYEE,
            $this->revocations->typeOf($this->employee),
        );
    }
}
