<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use App\Modules\Platform\Domain\Models\PublicProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M3-bis · Profil public du client (`public_profiles`).
 *
 * ## Pourquoi ce fichier existe
 *
 * `PUBLIC_PROFILE_DATA_MODEL.md` §3 déclare `public_profiles` « à créer », et
 * l'audit de couverture (`COULOIR16_AUDIT_COUVERTURE_GITHUB.md` §4) constate
 * qu'aucune migration ne la crée : la préférence de langue suit donc
 * l'APPAREIL, pas la personne. Ce fichier prouve que la table existe, que la
 * langue suit le COMPTE, et que les garde-fous du modèle de données tiennent.
 *
 * ## Les invariants protégés
 *
 * 1. **La langue suit le compte, pas l'appareil.** Deux sessions distinctes du
 *    même client lisent la même langue : c'est exactement ce qu'un stockage
 *    local ne sait pas faire.
 * 2. **Le consentement ne peut pas être « vrai » sans date.** La contrainte
 *    CHECK de la migration le refuse au niveau SQL — un UPDATE direct ne peut
 *    pas créer l'état incohérent.
 * 3. **Aucune donnée sensible.** Ni téléphone, ni coordonnée, ni identifiant
 *    de tenant dans la table : la règle d'architecture n°1 du couloir.
 * 4. **Le profil est isolé par client.** Un client ne lit jamais le profil
 *    d'un autre.
 */
class PublicProfileTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'email' => 'client-profil@example.test',
            'preferred_language' => 'fr',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Autorisation
    // ────────────────────────────────────────────────────────────────────────

    public function test_reading_the_public_profile_requires_a_real_user_api_token(): void
    {
        $this->getJson('/api/v1/user/public-profile')->assertStatus(401);

        $this->assertSame(0, DB::table('public_profiles')->count());
    }

    public function test_updating_the_public_profile_requires_a_real_user_api_token(): void
    {
        $this->patchJson('/api/v1/user/public-profile', [
            'preferred_locale' => 'ar',
        ])->assertStatus(401);

        $this->assertSame(0, DB::table('public_profiles')->count());
    }

    // ────────────────────────────────────────────────────────────────────────
    // Création à la première lecture
    // ────────────────────────────────────────────────────────────────────────

    public function test_first_read_creates_a_default_profile_instead_of_returning_404(): void
    {
        $this->actingAsClient();

        $response = $this->getJson('/api/v1/user/public-profile')->assertOk();

        $response->assertJsonPath('data.preferred_locale', 'fr');
        $response->assertJsonPath('data.notifications_opt_in', false);
        $response->assertJsonPath('data.supported_locales', ['fr', 'en', 'ar', 'tr']);

        $this->assertSame(1, DB::table('public_profiles')->count());
    }

    public function test_the_default_locale_is_taken_from_the_account_when_it_is_supported(): void
    {
        $this->client->forceFill(['preferred_language' => 'tr'])->save();

        $this->actingAsClient();

        $this->getJson('/api/v1/user/public-profile')
            ->assertOk()
            ->assertJsonPath('data.preferred_locale', 'tr');
    }

    public function test_an_unsupported_account_language_falls_back_to_french(): void
    {
        // `users.preferred_language` est en varchar(2) et peut contenir une
        // langue que la surface publique ne sait pas rendre. On ne recopie pas
        // aveuglément : on retombe sur `fr` plutôt que de créer un profil dans
        // une langue non servie.
        $this->client->forceFill(['preferred_language' => 'de'])->save();

        $this->actingAsClient();

        $this->getJson('/api/v1/user/public-profile')
            ->assertOk()
            ->assertJsonPath('data.preferred_locale', 'fr');
    }

    public function test_reading_twice_does_not_create_a_second_profile(): void
    {
        $this->actingAsClient();

        $this->getJson('/api/v1/user/public-profile')->assertOk();
        $this->getJson('/api/v1/user/public-profile')->assertOk();

        $this->assertSame(1, DB::table('public_profiles')->count());
    }

    // ────────────────────────────────────────────────────────────────────────
    // La langue suit le COMPTE, pas l'appareil
    // ────────────────────────────────────────────────────────────────────────

    public function test_the_language_follows_the_account_across_two_sessions(): void
    {
        $this->actingAsClient();

        $this->patchJson('/api/v1/user/public-profile', [
            'preferred_locale' => 'ar',
        ])->assertOk()->assertJsonPath('data.preferred_locale', 'ar');

        // Nouvelle session : nouveau jeton, guards oubliés. C'est ce que fait
        // un client qui change d'appareil — et c'est précisément le cas qu'un
        // stockage local ne couvre pas.
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();

        $this->actingAsClient();

        $this->getJson('/api/v1/user/public-profile')
            ->assertOk()
            ->assertJsonPath('data.preferred_locale', 'ar');
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $this->actingAsClient();

        $this->patchJson('/api/v1/user/public-profile', [
            'preferred_locale' => 'de',
        ])->assertStatus(422)->assertJsonValidationErrors('preferred_locale');

        $this->assertSame(0, DB::table('public_profiles')->count());
    }

    // ────────────────────────────────────────────────────────────────────────
    // Consentement : prouvable et daté
    // ────────────────────────────────────────────────────────────────────────

    public function test_opting_in_records_the_consent_date(): void
    {
        $this->actingAsClient();

        $response = $this->patchJson('/api/v1/user/public-profile', [
            'notifications_opt_in' => true,
            'privacy_policy_version' => '2026-11',
        ])->assertOk();

        $response->assertJsonPath('data.notifications_opt_in', true);
        $this->assertNotNull($response->json('data.notifications_consent_at'));
        $response->assertJsonPath('data.privacy_policy_version', '2026-11');
    }

    public function test_withdrawing_consent_clears_the_consent_date(): void
    {
        $this->actingAsClient();

        $this->patchJson('/api/v1/user/public-profile', [
            'notifications_opt_in' => true,
        ])->assertOk();

        $response = $this->patchJson('/api/v1/user/public-profile', [
            'notifications_opt_in' => false,
        ])->assertOk();

        $response->assertJsonPath('data.notifications_opt_in', false);
        $this->assertNull($response->json('data.notifications_consent_at'));
    }

    public function test_the_database_refuses_opt_in_without_a_consent_date(): void
    {
        // La contrainte CHECK est la garantie de dernier recours : même un
        // UPDATE direct, hors du contrôleur, ne peut pas créer l'état
        // incohérent que §3.1 du modèle de données interdit.
        $this->actingAsClient();
        $this->getJson('/api/v1/user/public-profile')->assertOk();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('public_profiles')
            ->where('user_id', $this->client->id)
            ->update(['notifications_opt_in' => true, 'notifications_consent_at' => null]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Isolation et absence de données sensibles
    // ────────────────────────────────────────────────────────────────────────

    public function test_a_client_never_reads_another_clients_profile(): void
    {
        $other = User::factory()->create(['email' => 'autre-client@example.test']);

        PublicProfile::query()->create([
            'user_id' => $other->id,
            'preferred_locale' => 'tr',
        ]);

        $this->actingAsClient();

        // Le profil rendu est celui du client authentifié, jamais celui de
        // l'autre — il n'y a d'ailleurs aucun paramètre pour le demander.
        $this->getJson('/api/v1/user/public-profile')
            ->assertOk()
            ->assertJsonPath('data.preferred_locale', 'fr');

        $this->assertSame('tr', PublicProfile::query()->find($other->id)?->preferred_locale);
    }

    public function test_the_profile_table_carries_no_phone_no_coordinates_and_no_tenant(): void
    {
        $columns = DB::select(
            "select column_name from information_schema.columns
             where table_schema = 'public' and table_name = 'public_profiles'"
        );

        $names = array_map(static fn ($row) => $row->column_name, $columns);

        foreach (['phone', 'phone_e164', 'latitude', 'longitude', 'company_id', 'tenant_id'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $names,
                "public_profiles ne doit jamais porter `{$forbidden}` (règle d'architecture n°1)."
            );
        }

        // Et les colonnes attendues sont bien là.
        foreach (['user_id', 'preferred_locale', 'approx_city', 'notifications_opt_in'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function test_deleting_the_account_cascades_to_the_public_profile(): void
    {
        $this->actingAsClient();
        $this->getJson('/api/v1/user/public-profile')->assertOk();

        $this->assertSame(1, DB::table('public_profiles')->count());

        DB::table('users')->where('id', $this->client->id)->delete();

        $this->assertSame(
            0,
            DB::table('public_profiles')->where('user_id', $this->client->id)->count(),
            'La suppression du compte doit emporter le profil (cascadeOnDelete).'
        );
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helper
    // ────────────────────────────────────────────────────────────────────────

    private function actingAsClient(): void
    {
        Auth::forgetGuards();

        $token = $this->client->createToken('public-profile-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();
    }
}
