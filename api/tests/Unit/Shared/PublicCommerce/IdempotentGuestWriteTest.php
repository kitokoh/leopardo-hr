<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\PublicCommerce;

use App\Shared\Services\PublicCommerce\IdempotentGuestWrite;
use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * BOS-050 (#8208) — idempotence d'écriture invitée : rejeu → existant,
 * création sinon, course 23505 → relecture du résultat du gagnant.
 */
class IdempotentGuestWriteTest extends TestCase
{
    private IdempotentGuestWrite $idempotent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idempotent = new IdempotentGuestWrite;
    }

    public function test_replay_creates_when_nothing_exists(): void
    {
        $calls = 0;

        /** @var array{result: string, created: bool} $replay */
        $replay = $this->idempotent->replay(
            fn (): ?string => null,
            function () use (&$calls): string {
                $calls++;

                return 'created-resource';
            }
        );

        $this->assertSame('created-resource', $replay['result']);
        $this->assertTrue($replay['created']);
        $this->assertSame(1, $calls);
    }

    public function test_replay_returns_existing_without_calling_create(): void
    {
        $calls = 0;

        /** @var array{result: string, created: bool} $replay */
        $replay = $this->idempotent->replay(
            fn (): string => 'existing-resource',
            function () use (&$calls): string {
                $calls++;

                return 'should-never-be-created';
            }
        );

        $this->assertSame('existing-resource', $replay['result']);
        $this->assertFalse($replay['created']);
        $this->assertSame(0, $calls);
    }

    public function test_replay_recovers_from_a_23505_race_by_rereading_the_winner(): void
    {
        $reads = 0;

        /** @var array{result: string, created: bool} $replay */
        $replay = $this->idempotent->replay(
            function () use (&$reads): ?string {
                $reads++;

                // 1re relecture : rien ; 2e relecture (après course) : le gagnant.
                return $reads > 1 ? 'winner-resource' : null;
            },
            fn (): string => throw new QueryException(
                'testing',
                'insert into orders (idempotency_key) values (?)',
                ['key-1'],
                new PDOException('SQLSTATE[23505]: Unique violation: 7 duplicate key value violates unique constraint "orders_company_id_idempotency_key_unique"')
            )
        );

        $this->assertSame('winner-resource', $replay['result']);
        $this->assertFalse($replay['created']);
    }

    public function test_replay_rethrows_a_23505_when_nothing_is_readable(): void
    {
        $this->expectException(QueryException::class);

        $this->idempotent->replay(
            fn (): ?string => null,
            fn (): string => throw new QueryException(
                'testing',
                'insert into orders (reference) values (?)',
                ['ref-1'],
                new PDOException('SQLSTATE[23505]: Unique violation: 7 duplicate key value violates unique constraint "orders_reference_unique"')
            )
        );
    }

    public function test_replay_rethrows_non_unique_errors(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->idempotent->replay(
            fn (): ?string => null,
            fn (): string => throw new RuntimeException('boom')
        );
    }

    public function test_is_unique_violation_detects_message_code_and_wrapped_exceptions(): void
    {
        $this->assertTrue(IdempotentGuestWrite::isUniqueViolation(
            new RuntimeException('SQLSTATE[23505]: Unique violation')
        ));
        $this->assertTrue(IdempotentGuestWrite::isUniqueViolation(
            new RuntimeException('wrapped', 0, new PDOException('SQLSTATE[23505]: Unique violation'))
        ));
        $this->assertFalse(IdempotentGuestWrite::isUniqueViolation(new RuntimeException('deadlock')));
        $this->assertFalse(IdempotentGuestWrite::isUniqueViolation(new RuntimeException('SQLSTATE[25P02]: aborted')));
    }
}
