<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Tranche #7595 — les leads d'acquisition de la vitrine deviennent LISIBLES.
 *
 * Constat verrouillé ici : `marketing_leads` recevait les 5 formulaires publics
 * (`POST /marketing/leads`) mais **aucune route GET n'existait** — un lead était
 * écrit puis invisible à l'admin. Ces tests fixent le contrat de la première
 * route de lecture :
 *
 *   1. authentification et permission exigées (401 / 403 fail-closed) ;
 *   2. les leads remontent réellement depuis la base, dans une enveloppe
 *      `{data, meta}` paginée ;
 *   3. `payload` arrive **décodé** — lu par le query builder, PDO rend un `jsonb`
 *      en chaîne, donc sans décodage la SPA recevrait le message de contact
 *      échappé (régression silencieuse du type « donnée présente mais
 *      inexploitable ») ;
 *   4. les filtres (type, statut, source, recherche) et la pagination bornée ;
 *   5. `to=YYYY-MM-DD` couvre la **journée entière** — une borne à minuit ferait
 *      disparaître une journée de leads sans erreur visible ;
 *   6. le miroir `/admin/marketing/leads` sert le même contrat (c'est celui que
 *      consomme la SPA admin-dashboard).
 */
class PlatformMarketingLeadsReadApiTest extends TestCase
{
    use CreatesMvpSchema;

    private const ENDPOINT = '/api/v1/platform/marketing/leads';

    private const ADMIN_ENDPOINT = '/api/v1/admin/marketing/leads';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        DB::table('marketing_leads')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('marketing_leads')->delete();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function actingAsPlatformRole(PlatformRole $role = PlatformRole::Marketing): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $role->value.'-leads@leopardo.test',
        ]);
        $superAdmin->forceFill([
            'password_hash' => Hash::make('password123'),
            // `platform_role` est hors $fillable (#3597) : forceFill obligatoire.
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedLead(array $overrides = []): void
    {
        static $sequence = 0;
        $sequence++;

        DB::table('marketing_leads')->insert(array_merge([
            'external_id' => 'lead-'.$sequence.'-'.uniqid(),
            'type' => 'contact',
            'email' => 'prospect'.$sequence.'@example.com',
            'locale' => 'fr',
            'status' => 'new',
            'crm_forwarded' => false,
            'email_forwarded' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------ autorisation

    public function test_an_anonymous_request_is_rejected(): void
    {
        $this->seedLead();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    public function test_a_platform_role_without_crm_view_is_rejected(): void
    {
        $this->actingAsPlatformRole(PlatformRole::Support);
        $this->seedLead();

        // Le rôle `support` ne porte pas `crm.view` (PlatformRole::permissions()).
        $this->getJson(self::ENDPOINT)
            ->assertForbidden()
            ->assertJsonPath('platform_role', 'support')
            ->assertJsonPath('required_permissions.0', 'crm.view');
    }

    public function test_a_tenant_employee_token_is_rejected(): void
    {
        // Fail-closed : la garde plateforme exige un compte SuperAdmin, un jeton
        // employé (guard sanctum) ne doit pas suffire.
        $this->actingAsPlatformRole(PlatformRole::Marketing);
        $this->seedLead();

        $anonymous = $this->getJson(self::ENDPOINT);
        $anonymous->assertOk();

        // Nouveau client HTTP : plus aucune identité.
        $this->app['auth']->forgetGuards();
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    // ------------------------------------------------------------------ lecture

    public function test_leads_are_listed_from_the_database_with_a_decoded_payload(): void
    {
        $this->actingAsPlatformRole();
        $this->seedLead([
            'type' => 'contact',
            'email' => 'fatima@example.dz',
            'page' => '/fr/contact',
            'source' => 'vitrine',
            'campaign' => 'rentree-2026',
            'referrer' => 'https://google.com/',
            'status' => 'new',
            'payload' => json_encode([
                'name' => 'Fatima M.',
                'company' => 'TechCorp',
                'message' => 'Bonjour, nous souhaitons une démo.',
            ]),
        ]);
        $this->seedLead(['type' => 'newsletter', 'email' => 'news@example.dz', 'status' => 'contacted']);

        $response = $this->getJson(self::ENDPOINT)->assertOk();

        $response
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.status_counts.new', 1)
            ->assertJsonPath('meta.status_counts.contacted', 1);

        // Le lead de contact est le plus recent (created_at = now() des deux
        // inserts) : on le retrouve par son email plutot que par sa position.
        $data = collect((array) $response->json('data'));
        $contact = $data->firstWhere('email', 'fatima@example.dz');

        $this->assertNotNull($contact, 'Le lead de contact doit etre present.');
        $this->assertSame('contact', $contact['type']);
        $this->assertSame('/fr/contact', $contact['page']);
        $this->assertSame('rentree-2026', $contact['campaign']);
        $this->assertSame('new', $contact['status']);

        // LE point de cette tranche : le payload est un TABLEAU, pas une chaine
        // JSON echappee. Sans decodePayload(), on aurait ici une string.
        $this->assertIsArray($contact['payload'], 'payload doit etre decode en tableau.');
        $this->assertSame('TechCorp', $contact['payload']['company']);
        $this->assertStringContainsString('démo', $contact['payload']['message']);
    }

    public function test_a_lead_without_payload_returns_null_and_not_an_empty_string(): void
    {
        $this->actingAsPlatformRole();
        $this->seedLead(['payload' => null]);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.payload', null);
    }

    // ------------------------------------------------------------------ filtres

    public function test_filters_by_type_status_source_and_search(): void
    {
        $this->actingAsPlatformRole();
        $this->seedLead(['type' => 'contact', 'email' => 'contact@a.dz', 'source' => 'vitrine', 'status' => 'new']);
        $this->seedLead(['type' => 'demo_request', 'email' => 'demo@b.dz', 'source' => 'vitrine', 'status' => 'new']);
        $this->seedLead(['type' => 'newsletter', 'email' => 'news@c.dz', 'source' => 'footer', 'status' => 'qualified']);

        $this->getJson(self::ENDPOINT.'?type=demo_request')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'demo@b.dz');

        $this->getJson(self::ENDPOINT.'?status=qualified')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'newsletter');

        $this->getJson(self::ENDPOINT.'?source=footer')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Recherche : elle doit traverser l'e-mail, pas seulement le nom.
        $this->getJson(self::ENDPOINT.'?search=demo@')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'demo@b.dz');

        // Les compteurs d'onglets restent GLOBAUX meme quand un filtre est actif.
        $this->getJson(self::ENDPOINT.'?type=newsletter')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts.new', 2);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->actingAsPlatformRole();

        $this->getJson(self::ENDPOINT.'?type=not_a_type')->assertStatus(422);
        $this->getJson(self::ENDPOINT.'?status=not_a_status')->assertStatus(422);
        $this->getJson(self::ENDPOINT.'?per_page=101')->assertStatus(422);
        $this->getJson(self::ENDPOINT.'?from=not-a-date')->assertStatus(422);
    }

    public function test_pagination_is_capped_and_reported(): void
    {
        $this->actingAsPlatformRole();
        foreach (range(1, 3) as $i) {
            $this->seedLead(['email' => "paginate{$i}@example.dz", 'created_at' => now()->subMinutes($i)]);
        }

        $this->getJson(self::ENDPOINT.'?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        $this->getJson(self::ENDPOINT.'?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------- périodes

    public function test_to_covers_the_whole_day_and_not_only_midnight(): void
    {
        $this->actingAsPlatformRole();
        // 12:00 le 17 : inclus par `to=2026-09-17`, EXCLU si la borne etait
        // posee a minuit (00:00). C'est exactement la regression visee.
        $this->seedLead(['email' => 'jour17@example.dz', 'created_at' => '2026-09-17 12:00:00']);
        $this->seedLead(['email' => 'jour19@example.dz', 'created_at' => '2026-09-19 12:00:00']);

        $response = $this->getJson(self::ENDPOINT.'?from=2026-09-17&to=2026-09-17')->assertOk();

        $emails = collect((array) $response->json('data'))->pluck('email')->all();
        $this->assertContains('jour17@example.dz', $emails);
        $this->assertNotContains('jour19@example.dz', $emails);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_from_starts_at_the_beginning_of_the_day(): void
    {
        $this->actingAsPlatformRole();
        $this->seedLead(['email' => 'veille@example.dz', 'created_at' => '2026-09-16 23:00:00']);
        $this->seedLead(['email' => 'jour@example.dz', 'created_at' => '2026-09-17 08:00:00']);

        $response = $this->getJson(self::ENDPOINT.'?from=2026-09-17')->assertOk();

        $emails = collect((array) $response->json('data'))->pluck('email')->all();
        $this->assertContains('jour@example.dz', $emails);
        $this->assertNotContains('veille@example.dz', $emails);
    }

    // ------------------------------------------------------------------ miroir

    public function test_the_admin_mirror_route_serves_the_same_contract(): void
    {
        $this->actingAsPlatformRole();
        $this->seedLead(['email' => 'miroir@example.dz', 'type' => 'demo_request']);

        $this->getJson(self::ADMIN_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'miroir@example.dz');
    }

    public function test_the_admin_mirror_is_also_permission_gated(): void
    {
        $this->actingAsPlatformRole(PlatformRole::Finance);

        $this->getJson(self::ADMIN_ENDPOINT)->assertForbidden();
    }
}
