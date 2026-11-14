<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\User;
use App\Modules\TravelAgency\Application\Services\PublicIdentityBridge;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — ADR-IDENTITE-CLIENTS-PUBLICS §8.3-3 · Piste d'audit du
 * rattachement d'identité.
 *
 * ## Pourquoi ce fichier existe
 *
 * L'ADR §8.3 exige trois propriétés du service de rattachement, dont la
 * troisième : « **Audit systématique.** Chaque écriture produit une ligne
 * `audit_logs` : acteur, ancien `user_id`, nouveau, numéro, motif,
 * horodatage. » L'audit de couverture (`COULOIR16_AUDIT_COUVERTURE_GITHUB.md`
 * §4) constate que `PublicIdentityBridge` n'écrivait **aucune** ligne : la
 * propriété était déclarée, pas tenue.
 *
 * ## Ce que ces tests prouvent
 *
 * 1. **Chaque rattachement laisse une trace** — et une seule.
 * 2. **La trace dit d'où l'on vient** : l'ancien `user_id` est capturé AVANT
 *    l'écriture. Un journal qui ne dit que l'état final ne permet pas de
 *    défaire un rattachement erroné.
 * 3. **La trace porte la preuve** : le numéro vérifié, sans lequel la ligne ne
 *    dit pas pourquoi les deux identités ont été jointes.
 * 4. **Un rattachement idempotent n'écrit pas de seconde ligne** : rejouer le
 *    même couple n'est pas un nouvel événement.
 * 5. **L'audit ne peut pas casser le rattachement** : si le journal est
 *    indisponible, `user_id` reste écrit et l'échec part dans le log
 *    applicatif — jamais l'inverse (un `user_id` écrit sans trace).
 */
final class PublicIdentityBridgeAuditTest extends TestCase
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
            'name' => 'Client Audit',
            'email' => $email,
            'phone' => $phone,
            'phone_e164' => $phone,
            'phone_verified_at' => $verified ? now() : null,
            'password' => bcrypt('secret'),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // §8.3-3 — chaque écriture produit une ligne
    // ────────────────────────────────────────────────────────────────────────

    public function test_linking_writes_exactly_one_audit_line(): void
    {
        $account = $this->account('audit1@example.test', '+221770001001', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('audit1@example.test', '+221770001001');

        $this->assertSame(0, AuditLog::query()->count(), 'Aucune ligne avant le rattachement.');

        $this->bridge->link($account, $user);

        $this->assertSame(1, AuditLog::query()->count(), 'Un rattachement = une ligne d\'audit.');
    }

    public function test_the_audit_line_carries_the_actor_the_old_and_the_new_user_id(): void
    {
        $account = $this->account('audit2@example.test', '+221770001002', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('audit2@example.test', '+221770001002');

        $this->bridge->link($account, $user);

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame('public_identity.attached', $log->action);
        $this->assertSame('public_identity', $log->module);

        // Acteur : le `users` rattaché.
        $this->assertSame($user->id, $log->user_id);

        // Ancien état : NULL (le compte n'était rattaché à personne).
        // `array_key_exists` et non `??` : `??` traiterait un `null` légitime
        // comme une clé absente, et l'assertion ne testerait plus rien.
        $this->assertArrayHasKey('user_id', $log->old_values);
        $this->assertNull($log->old_values['user_id']);

        // Nouvel état : le `users` rattaché.
        $this->assertSame($user->id, $log->new_values['user_id']);

        // Cible : le compte grand public.
        $this->assertSame(TravelCustomerAccount::class, $log->auditable_type);
        $this->assertSame((string) $account->id, $log->auditable_id);
    }

    public function test_the_audit_line_carries_the_verified_phone_as_proof(): void
    {
        $account = $this->account('audit3@example.test', '+221770001003', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('audit3@example.test', '+221770001003');

        $this->bridge->link($account, $user);

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame('+221770001003', $log->metadata['verified_phone'] ?? null);
        $this->assertSame('verified_phone_bridge', $log->metadata['reason'] ?? null);
    }

    public function test_the_audit_line_is_a_platform_event_with_no_tenant(): void
    {
        $account = $this->account('audit4@example.test', '+221770001004', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('audit4@example.test', '+221770001004');

        $this->bridge->link($account, $user);

        $log = AuditLog::query()->firstOrFail();

        // Le rattachement n'appartient à aucun tenant : `company_id` NULL est
        // la marque d'un événement plateforme.
        $this->assertNull($log->company_id);
    }

    // ────────────────────────────────────────────────────────────────────────
    // L'ancien `user_id` est capturé AVANT l'écriture
    // ────────────────────────────────────────────────────────────────────────

    public function test_relinking_to_another_user_records_the_previous_user_id(): void
    {
        $account = $this->account('audit5@example.test', '+221770001005', verified: true);

        $first = User::factory()->create(['email' => 'premier@example.test']);
        $second = User::factory()->create(['email' => 'second@example.test']);

        $this->bridge->link($account, $first);

        // Re-rattachement vers un AUTRE `users` : la ligne doit dire d'où
        // l'on vient, sinon un rattachement erroné est indéfaisable.
        $this->bridge->link($account->refresh(), $second);

        $logs = AuditLog::query()->orderBy('id')->get();

        $this->assertCount(2, $logs, 'Deux rattachements distincts = deux lignes.');

        $this->assertSame($first->id, $logs[1]->old_values['user_id']);
        $this->assertSame($second->id, $logs[1]->new_values['user_id']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Idempotence : rejouer n'est pas un nouvel événement
    // ────────────────────────────────────────────────────────────────────────

    public function test_relinking_the_same_pair_writes_no_second_audit_line(): void
    {
        $account = $this->account('audit6@example.test', '+221770001006', verified: true);
        $user = $this->bridge->resolveUserForPublicAccount('audit6@example.test', '+221770001006');

        $first = $this->bridge->link($account, $user);
        $this->bridge->link($first, $user);

        $this->assertSame(
            1,
            AuditLog::query()->count(),
            'Rejouer le même couple n\'est pas un nouvel événement : une seule ligne.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────
    // Un refus n'écrit rien
    // ────────────────────────────────────────────────────────────────────────

    public function test_a_refused_link_writes_no_audit_line(): void
    {
        // Téléphone non vérifié : `link()` refuse AVANT d'écrire.
        $account = $this->account('audit7@example.test', '+221770001007', verified: false);
        $user = User::factory()->create(['email' => 'refus@example.test']);

        try {
            $this->bridge->link($account, $user);
            $this->fail('Un téléphone non vérifié doit être refusé.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('public_identity_unverified_phone', $e->getMessage());
        }

        $this->assertSame(
            0,
            AuditLog::query()->count(),
            'Un rattachement refusé n\'est pas un événement : aucune ligne.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────
    // Ce que ces tests ne prouvent PAS
    // ────────────────────────────────────────────────────────────────────────
    //
    // La résilience du journal (`recordAttachment()` enveloppe l'écriture dans
    // un `try/catch` : un journal indisponible ne doit pas annuler un
    // rattachement déjà écrit) n'est **pas** couverte par un test exécuté.
    //
    // Tentative réelle : renommer `audit_logs` puis relier. Elle échoue pour
    // une raison de harnais, pas de code — `RefreshDatabase` exécute chaque
    // test dans une transaction, et un `ALTER TABLE` en échec place PostgreSQL
    // en `25P02` (« current transaction is aborted »), ce qui rend le
    // `RENAME` de restauration impossible. Le test ne mesurerait donc rien de
    // la garde applicative.
    //
    // La garde est donc **lue, pas exécutée** : elle est déclarée ici comme
    // telle plutôt que présentée comme prouvée.
}
