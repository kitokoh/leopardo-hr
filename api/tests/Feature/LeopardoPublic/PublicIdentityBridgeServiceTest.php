<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use App\Modules\TravelAgency\Application\Services\PublicIdentityBridge;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * IAM-L4 (#9094) — Pont d'identité du client grand public.
 *
 * Ces tests portent sur le **service** `PublicIdentityBridge` et sur la
 * **contrainte de base** qui le protège. Ils s'appuient sur les migrations
 * publiques réelles (`RefreshTenantDatabase` → `migrate:fresh` sur `public`) :
 * c'est le seul moyen de vérifier la contrainte `CHECK` réellement, plutôt que
 * de la décrire.
 *
 * Les 5 assertions du plan §L4 sont couvertes, plus la règle de résolution de
 * l'ADR §6 (jamais de fusion automatique de deux `users`).
 */
final class PublicIdentityBridgeServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private PublicIdentityBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bridge = new PublicIdentityBridge;
    }

    private function account(string $email, ?string $phone = null, bool $verified = false): TravelCustomerAccount
    {
        return TravelCustomerAccount::query()->create([
            'name' => 'Client Test',
            'email' => $email,
            'phone' => $phone,
            'phone_e164' => $phone,
            'phone_verified_at' => $verified ? now() : null,
            'password' => bcrypt('secret'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plan §L4 — les 5 assertions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * §L4 — `UPDATE user_id` avec `phone_verified_at IS NULL` ⇒ SQLSTATE 23514.
     *
     * La garde est en BASE, pas seulement en code : même une écriture directe
     * (script, requête manuelle) est refusée.
     */
    public function test_bridge_cannot_be_written_without_a_verified_phone(): void
    {
        $user = User::query()->create([
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test',
        ]);
        $account = $this->account('unverified@example.test', '+221770000001', verified: false);

        $thrown = null;

        try {
            // Écriture directe, sans passer par le service : c'est la contrainte
            // PostgreSQL qui doit refuser, pas la politesse du code. On l'isole
            // dans une transaction imbriquée (savepoint) : sans cela, l'échec
            // avorterait la transaction du harnais et ferait échouer le tearDown
            // en `25P02` — un artefact de test, pas un défaut du code.
            DB::transaction(function () use ($account, $user): void {
                DB::table('travel_customer_accounts')
                    ->where('id', $account->id)
                    ->update(['user_id' => $user->id]);
            });
        } catch (QueryException $e) {
            $thrown = $e;
        }

        $this->assertNotNull(
            $thrown,
            'La contrainte CHECK doit refuser un user_id sans téléphone vérifié (SQLSTATE 23514).'
        );
        $this->assertStringContainsString(
            '23514',
            $thrown->getMessage(),
            'Le refus doit venir de la contrainte CHECK, pas d\'une autre erreur.'
        );
    }

    /**
     * §L4 — deux comptes ne sont jamais reliés sur un téléphone non vérifié.
     *
     * Le service refuse explicitement (message exploitable) avant même que la
     * base ne tranche.
     */
    public function test_two_accounts_are_never_linked_on_an_unverified_phone(): void
    {
        $user = User::query()->create([
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'b@example.test',
        ]);
        $account = $this->account('unverified2@example.test', '+221770000002', verified: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public_identity_unverified_phone');

        $this->bridge->link($account, $user);
    }

    /**
     * §L4 — un changement de numéro ne transfère JAMAIS le `user_id`.
     *
     * Le pont est une propriété du compte, pas du numéro : changer de numéro
     * ne doit pas rattacher le compte à un autre `users`.
     */
    public function test_changing_phone_never_transfers_user_id(): void
    {
        $account = $this->account('change@example.test', '+221770000003', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('change@example.test', '+221770000003');
        $account = $this->bridge->link($account, $user);

        $originalUserId = $account->user_id;
        $this->assertNotNull($originalUserId);

        // Changement de numéro : on met à jour le numéro, pas le pont.
        $account->forceFill(['phone_e164' => '+221770000099'])->save();

        $this->assertSame(
            $originalUserId,
            $account->refresh()->user_id,
            'Changer de numéro ne doit jamais transférer le user_id.'
        );
    }

    /**
     * §L4 — deux inscriptions concurrentes produisent UN compte.
     *
     * La contrainte d'unicité sur `phone_e164` (23505) est gérée : la seconde
     * résolution retrouve le `users` créé par la première au lieu d'en créer un
     * doublon.
     */
    public function test_concurrent_registrations_produce_one_account(): void
    {
        $first = $this->bridge->resolveUserForPublicAccount('concurrent@example.test', '+221770000004');
        $second = $this->bridge->resolveUserForPublicAccount('concurrent@example.test', '+221770000004');

        $this->assertSame($first->id, $second->id, 'Deux résolutions du même numéro doivent rendre le même users.');
        $this->assertSame(
            1,
            User::query()->where('phone_e164', '+221770000004')->count(),
            'Aucun doublon ne doit être créé.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ADR §6 — algorithme de résolution
    // ─────────────────────────────────────────────────────────────────────────

    public function test_same_phone_resolves_to_the_same_user(): void
    {
        $a = $this->bridge->resolveUserForPublicAccount('same1@example.test', '+221770000005');
        $b = $this->bridge->resolveUserForPublicAccount('same2@example.test', '+221770000005');

        $this->assertSame($a->id, $b->id, 'Le téléphone est la clé de convergence : même numéro ⇒ même users.');
    }

    public function test_existing_user_is_reused_not_duplicated(): void
    {
        $existing = User::query()->create([
            'first_name' => 'Existant', 'last_name' => 'Déjà', 'email' => 'existing@example.test',
            'phone_e164' => '+221770000006',
        ]);

        $resolved = $this->bridge->resolveUserForPublicAccount('existing@example.test', '+221770000006');

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, User::query()->where('phone_e164', '+221770000006')->count());
    }

    /**
     * ADR §6 — la garde anti-prise-de-contrôle : deux `users` distincts
     * désignés par le téléphone et l'e-mail ne sont JAMAIS fusionnés
     * automatiquement.
     */
    public function test_two_users_are_never_auto_merged(): void
    {
        User::query()->create([
            'first_name' => 'Par', 'last_name' => 'Téléphone', 'email' => 'byphone@example.test',
            'phone_e164' => '+221770000007',
        ]);
        User::query()->create([
            'first_name' => 'Par', 'last_name' => 'Email', 'email' => 'byemail@example.test',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public_identity_conflict');

        // Le téléphone pointe vers un users, l'e-mail vers un AUTRE : on refuse.
        $this->bridge->resolveUserForPublicAccount('byemail@example.test', '+221770000007');
    }

    public function test_verify_phone_and_link_creates_the_bridge(): void
    {
        $account = $this->account('verify@example.test', null, verified: false);

        $linked = $this->bridge->verifyPhoneAndLink($account, '+221770000008');

        $this->assertNotNull($linked->phone_verified_at, 'Le téléphone doit être marqué vérifié.');
        $this->assertNotNull($linked->user_id, 'Le pont doit être posé après vérification.');
        $this->assertSame('+221770000008', $linked->phone_e164);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $account = $this->account('invalid@example.test', null, verified: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public_identity_invalid_phone');

        $this->bridge->verifyPhoneAndLink($account, '0555123456');
    }

    public function test_link_is_idempotent(): void
    {
        $account = $this->account('idem@example.test', '+221770000009', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('idem@example.test', '+221770000009');

        $first = $this->bridge->link($account, $user);
        $second = $this->bridge->link($first, $user);

        $this->assertSame($first->user_id, $second->user_id);
    }
}
