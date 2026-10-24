<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-03 IDENTITE (#8736) — le telephone est un identifiant de premier rang.
 *
 * L'ecart P-5 de l'audit readiness : `users.phone` n'avait ni unicite ni index,
 * donc un OTP par SMS ne pouvait pas identifier un compte de facon fiable et le
 * dedoublonnage d'un client final multi-app etait impossible.
 *
 * Ce test verrouille les deux criteres d'acceptation qui ne peuvent pas etre
 * prouves par une validation applicative : le doublon est rejete par **la
 * base** (violation de contrainte), et deux `NULL` restent autorises (index
 * unique **partiel**). Un test qui ne verifierait que la validation applicative
 * passerait meme si la contrainte manquait — c'est exactement le defaut que
 * l'audit a releve.
 */
class PhoneE164UniquenessTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_normalized_duplicate_is_rejected_by_the_database(): void
    {
        // Deux ecritures DIFFERENTES du meme numero : c'est le cas que la
        // colonne brute ne pouvait pas detecter.
        $first = PhoneNumber::toE164('0555 12 34 56', 'DZ');
        $second = PhoneNumber::toE164('+213555123456', 'DZ');

        $this->assertSame($first, $second, 'Les deux ecritures doivent converger avant le test.');

        User::factory()->create(['phone_e164' => $first]);

        $rejected = false;

        // Le doublon est tente dans un SAVEPOINT : la violation de contrainte
        // avorte la sous-transaction, pas la transaction de test (sinon
        // PostgreSQL laisse la session en 25P02 et tous les tests suivants
        // echouent en cascade).
        try {
            DB::transaction(function () use ($second): void {
                User::factory()->create(['phone_e164' => $second]);
            });
        } catch (QueryException) {
            $rejected = true;
        }

        $this->assertTrue(
            $rejected,
            'Un doublon de numero normalise doit etre rejete par la BASE, pas seulement par une validation applicative.',
        );

        $this->assertSame(1, User::query()->where('phone_e164', $first)->count());
    }

    public function test_two_null_phone_numbers_are_allowed(): void
    {
        // Index unique PARTIEL : la majorite des comptes n'a pas de telephone,
        // et un index plein interdirait plus d'un compte sans numero.
        User::factory()->create(['phone_e164' => null]);
        User::factory()->create(['phone_e164' => null]);

        $this->assertSame(2, User::query()->whereNull('phone_e164')->count());
    }

    public function test_distinct_normalized_numbers_coexist(): void
    {
        User::factory()->create(['phone_e164' => PhoneNumber::toE164('0555123456', 'DZ')]);
        User::factory()->create(['phone_e164' => PhoneNumber::toE164('0555987654', 'DZ')]);

        $this->assertSame(2, User::query()->whereNotNull('phone_e164')->count());
    }

    public function test_the_unique_index_is_partial(): void
    {
        // Preuve structurelle : l'index existe ET porte une clause `WHERE`.
        // Un index plein passerait les tests ci-dessus tout en interdisant le
        // second compte sans telephone — d'ou cette verification explicite.
        /** @var object{indexdef: string}|null $row */
        $row = DB::selectOne(
            "SELECT indexdef FROM pg_indexes WHERE schemaname = 'public' AND indexname = 'users_phone_e164_unique'"
        );

        $this->assertNotNull($row, "L'index unique users_phone_e164_unique doit exister.");
        $this->assertStringContainsString('UNIQUE', $row->indexdef);
        $this->assertStringContainsString('WHERE', $row->indexdef);
        $this->assertStringContainsString('phone_e164 IS NOT NULL', $row->indexdef);
    }

    public function test_phone_e164_column_is_wide_enough_for_e164(): void
    {
        // E.164 fait au plus 16 caracteres (`+` et 15 chiffres) : une colonne
        // plus courte tronquerait silencieusement un numero long.
        /** @var object{character_maximum_length: int|string|null}|null $row */
        $row = DB::selectOne(
            "SELECT character_maximum_length FROM information_schema.columns "
            ."WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'phone_e164'"
        );

        $this->assertNotNull($row, 'La colonne phone_e164 doit exister.');
        $this->assertGreaterThanOrEqual(16, (int) $row->character_maximum_length);
    }
}
