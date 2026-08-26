<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Application\Actions\RefreshTokenAction;
use App\Core\Auth\Domain\Exceptions\TokenRotationConflictException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #5581 — Rotation de token atomique.
 *
 * Le verrou pessimiste (transaction + `SELECT ... FOR UPDATE`) de
 * `RefreshTokenAction` sérialise les rotations concurrentes : une seule
 * requête émet le nouveau token, les suivantes (même token source) sont
 * rejetées en 409 TOKEN_ALREADY_ROTATED. Fini la duplication de tokens
 * valides dans la fenêtre de rafraîchissement (24 h avant expiration).
 */
class TokenRotationAtomicityTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_single_rotation_succeeds_and_returns_new_token(): void
    {
        $token = $this->manager->createToken('api', ['*']);
        $this->manager->withAccessToken($token->accessToken);

        $action = app(RefreshTokenAction::class);
        $result = $action->execute($this->manager);

        $this->assertNotEmpty($result['token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertNotSame($token->plainTextToken, $result['token']);
        $this->assertSame(0, $this->manager->tokens()->where('id', $token->accessToken->getKey())->count());
    }

    public function test_concurrent_rotation_of_same_token_is_rejected(): void
    {
        $token = $this->manager->createToken('api', ['*']);
        $this->manager->withAccessToken($token->accessToken);

        $action = app(RefreshTokenAction::class);
        $action->execute($this->manager);

        // 2e rotation concurrente du MÊME token (modèle en mémoire stale) :
        // la ligne a été supprimée par la 1re rotation → refus 409.
        $this->expectException(TokenRotationConflictException::class);
        $this->expectExceptionCode(409);

        $action->execute($this->manager);
    }
}
