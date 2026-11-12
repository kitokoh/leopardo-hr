<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M2 (#9104) · Jetons d'appareil du grand public (push).
 *
 * ## Pourquoi ce fichier existe
 *
 * `PublicDeviceTokenController` expose `POST` et `DELETE /user/device-tokens`
 * et **n'avait aucun test** (recherche plein texte de la route dans `tests/` :
 * 0 occurrence). Or c'est la seule table publique qui relie un appareil à un
 * compte : une erreur ici envoie les notifications d'une personne à une autre.
 *
 * ## Les deux invariants que ces tests protègent
 *
 * 1. **Le `user_id` vient du jeton, jamais du corps.** Un client ne peut pas
 *    enregistrer un appareil pour quelqu'un d'autre — sinon il suffirait de
 *    poster le `user_id` de la victime pour recevoir ses notifications.
 * 2. **Un appareil qui change de propriétaire est réaffecté.** Le jeton est
 *    unique en base ; si l'ancien propriétaire restait rattaché, il
 *    continuerait de recevoir les notifications du nouveau.
 *
 * ## Ce que ces tests ne prouvent pas
 *
 * Aucun envoi FCM/APNs n'est testé : le contrôleur n'envoie rien (c'est écrit
 * dans son en-tête). L'envoi réel dépend d'un compte de développeur — porte (c),
 * aucune dépense engagée.
 */
class PublicDeviceTokenTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'email' => 'client-device@example.test',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Autorisation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_registering_a_device_token_requires_a_real_user_api_token(): void
    {
        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-anonyme',
            'platform' => 'android',
        ])->assertStatus(401);

        $this->assertSame(0, DB::table('public_device_tokens')->count());
    }

    public function test_deleting_a_device_token_requires_a_real_user_api_token(): void
    {
        $this->deleteJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-anonyme',
        ])->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enregistrement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_device_token_is_registered_against_the_authenticated_user(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
            'device_name' => 'Pixel de test',
        ])->assertStatus(201)
            ->assertJsonPath('status', 'registered');

        $row = DB::table('public_device_tokens')->where('token', 'fcm-token-abc')->first();

        $this->assertNotNull($row);
        $this->assertSame($this->client->id, (int) $row->user_id);
        $this->assertSame('android', $row->platform);
        $this->assertSame('Pixel de test', $row->device_name);
        $this->assertNotNull($row->last_seen_at);
    }

    public function test_the_user_id_in_the_body_is_ignored(): void
    {
        // La victime : un autre compte, qui ne doit JAMAIS recevoir ce jeton.
        $victim = User::factory()->create(['email' => 'victime@example.test']);

        $this->actingAsClient();

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-usurpe',
            'platform' => 'ios',
            // Tentative d'usurpation : ce champ ne doit pas être lu.
            'user_id' => $victim->id,
        ])->assertStatus(201);

        $row = DB::table('public_device_tokens')->where('token', 'fcm-token-usurpe')->first();

        $this->assertNotNull($row);
        $this->assertSame($this->client->id, (int) $row->user_id);
        $this->assertNotSame($victim->id, (int) $row->user_id);
    }

    public function test_registering_the_same_token_twice_updates_instead_of_duplicating(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-idem',
            'platform' => 'android',
            'device_name' => 'Ancien nom',
        ])->assertStatus(201);

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-idem',
            'platform' => 'android',
            'device_name' => 'Nouveau nom',
        ])->assertStatus(201);

        // Une seule ligne : le jeton est unique en base.
        $this->assertSame(
            1,
            DB::table('public_device_tokens')->where('token', 'fcm-token-idem')->count(),
        );

        $row = DB::table('public_device_tokens')->where('token', 'fcm-token-idem')->first();
        $this->assertNotNull($row);
        $this->assertSame('Nouveau nom', $row->device_name);
    }

    public function test_a_device_that_changes_owner_is_reassigned(): void
    {
        // Un téléphone revendu / réinitialisé : le même jeton FCM arrive sur un
        // autre compte. L'ancien propriétaire ne doit plus être rattaché, sinon
        // il continuerait de recevoir les notifications du nouveau.
        $previousOwner = User::factory()->create(['email' => 'ancien-proprietaire@example.test']);

        DB::table('public_device_tokens')->insert([
            'user_id' => $previousOwner->id,
            'token' => 'fcm-token-revendu',
            'platform' => 'android',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsClient();

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-revendu',
            'platform' => 'android',
        ])->assertStatus(201);

        $row = DB::table('public_device_tokens')->where('token', 'fcm-token-revendu')->first();
        $this->assertNotNull($row);

        $this->assertSame($this->client->id, (int) $row->user_id);
        $this->assertNotSame($previousOwner->id, (int) $row->user_id);
    }

    public function test_an_unknown_platform_is_refused(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-plateforme',
            'platform' => 'blackberry',
        ])->assertStatus(422);

        $this->assertSame(
            0,
            DB::table('public_device_tokens')->where('token', 'fcm-token-plateforme')->count(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Suppression
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_client_can_unregister_their_own_device(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-a-supprimer',
            'platform' => 'web',
        ])->assertStatus(201);

        $this->deleteJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-a-supprimer',
        ])->assertStatus(204);

        $this->assertSame(
            0,
            DB::table('public_device_tokens')->where('token', 'fcm-token-a-supprimer')->count(),
        );
    }

    public function test_a_client_cannot_unregister_another_users_device(): void
    {
        $other = User::factory()->create(['email' => 'autre-appareil@example.test']);

        DB::table('public_device_tokens')->insert([
            'user_id' => $other->id,
            'token' => 'fcm-token-dautrui',
            'platform' => 'ios',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsClient();

        // 204 (idempotent) mais la ligne d'autrui doit survivre : la clause
        // `user_id` est dans la requête, pas dans une vérification préalable.
        $this->deleteJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-dautrui',
        ])->assertStatus(204);

        $this->assertSame(
            1,
            DB::table('public_device_tokens')->where('token', 'fcm-token-dautrui')->count(),
        );
    }

    public function test_unregistering_an_unknown_token_is_idempotent(): void
    {
        $this->actingAsClient();

        $this->deleteJson('/api/v1/user/device-tokens', [
            'token' => 'fcm-token-jamais-vu',
        ])->assertStatus(204);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsClient(): void
    {
        Auth::forgetGuards();

        $token = $this->client->createToken('device-token-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();
    }
}
