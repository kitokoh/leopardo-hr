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
 * #8440 — espace « Prendre des parts » (page vitrine `/investir`, entrée
 * footer discrète) : les manifestations d'intérêt de prise de parts dans
 * l'entreprise voyagent par le canal leads existant (`marketing_leads`,
 * type `equity_interest` — pattern `solution_survey` #6692) et se suivent
 * dans l'admin plateforme.
 *
 * Contrat verrouillé ici :
 *
 *   1. la capture accepte le type `equity_interest` (contrainte CHECK
 *      étendue + liste de validation) avec le détail en `payload` jsonb
 *      (nom, téléphone, tranche de montant, message, consentement) ;
 *   2. la liste plateforme filtre par type et expose des compteurs de
 *      statut **scopés au type** (`meta.type_status_counts`) sans toucher
 *      au contrat global de `meta.status_counts` (#7595) ;
 *   3. la qualification (statut + note interne) se fait par PATCH, en
 *      fail-closed RBAC (`crm.view` : rôle marketing inclus, support exclu).
 */
class EquityInterestLeadApiTest extends TestCase
{
    use CreatesMvpSchema;

    private const CAPTURE_ENDPOINT = '/api/v1/marketing/leads';

    private const LIST_ENDPOINT = '/api/v1/platform/marketing/leads';

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
            'email' => $role->value.'-equity@leopardo.test',
        ]);
        $superAdmin->forceFill([
            'password_hash' => Hash::make('password123'),
            // `platform_role` est hors $fillable (#3597) : forceFill obligatoire.
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    /** @param array<string, mixed> $overrides */
    private function captureEquityInterest(array $overrides = []): string
    {
        $payload = array_merge([
            'external_id' => 'equity-'.bin2hex(random_bytes(6)),
            'type' => 'equity_interest',
            'email' => 'investisseur@example.com',
            'locale' => 'fr',
            'country' => 'FR',
            'page' => '/investir',
            'source' => 'equity_form',
            'payload' => [
                'name' => 'Awa Diallo',
                'phone' => '+33600000000',
                'amount_bracket' => '10k_50k',
                'message' => 'Intéressée par une prise de participation minoritaire.',
                'consent' => true,
            ],
        ], $overrides);

        $this->postJson(self::CAPTURE_ENDPOINT, $payload)->assertCreated();

        return (string) $payload['email'];
    }

    // ---------------------------------------------------------------- capture

    public function test_capture_accepts_equity_interest_type_with_payload(): void
    {
        $this->captureEquityInterest();

        $this->assertDatabaseHas('marketing_leads', [
            'type' => 'equity_interest',
            'email' => 'investisseur@example.com',
            'status' => 'new',
        ]);

        $lead = DB::table('marketing_leads')->where('type', 'equity_interest')->first();
        $decoded = json_decode((string) $lead->payload, true);
        $this->assertSame('10k_50k', $decoded['amount_bracket']);
        $this->assertTrue($decoded['consent']);
    }

    public function test_capture_still_rejects_unknown_type(): void
    {
        $this->postJson(self::CAPTURE_ENDPOINT, [
            'external_id' => 'equity-x',
            'type' => 'seed_round',
            'email' => 'investisseur@example.com',
        ])->assertUnprocessable();
    }

    // ---------------------------------------------------------------- lecture

    public function test_index_filters_by_equity_type_and_scopes_status_counts(): void
    {
        $this->captureEquityInterest(['email' => 'investisseur@example.com']);
        $this->postJson(self::CAPTURE_ENDPOINT, [
            'external_id' => 'demo-'.bin2hex(random_bytes(6)),
            'type' => 'demo_request',
            'email' => 'prospect@example.com',
        ])->assertCreated();

        $this->actingAsPlatformRole();

        $response = $this->getJson(self::LIST_ENDPOINT.'?type=equity_interest')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'investisseur@example.com');
        $response->assertJsonPath('data.0.payload.amount_bracket', '10k_50k');
        // Compteurs scopés au type (#8440) : l'onglet « new » de l'écran
        // « Prendre des parts » ne doit pas compter les démos/signups.
        $response->assertJsonPath('meta.type_status_counts.new', 1);
        // Le contrat global (#7595) reste intact : tous types confondus.
        $response->assertJsonPath('meta.status_counts.new', 2);

        // Sans filtre de type : pas de compteurs scopés (clé nulle explicite).
        $this->getJson(self::LIST_ENDPOINT)->assertOk()
            ->assertJsonPath('meta.type_status_counts', null);
    }

    // ---------------------------------------------------------------- suivi

    public function test_update_transitions_status_and_note(): void
    {
        $this->captureEquityInterest();
        $this->actingAsPlatformRole();

        $id = (int) DB::table('marketing_leads')->where('type', 'equity_interest')->value('id');

        $this->patchJson(self::LIST_ENDPOINT.'/'.$id, [
            'status' => 'contacted',
            'note' => 'Échange téléphonique planifié — dossier à préparer.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.note', 'Échange téléphonique planifié — dossier à préparer.');

        $this->assertDatabaseHas('marketing_leads', ['id' => $id, 'status' => 'contacted']);
    }

    public function test_update_rejects_invalid_payload_and_unknown_lead(): void
    {
        $this->captureEquityInterest();
        $this->actingAsPlatformRole();

        $id = (int) DB::table('marketing_leads')->where('type', 'equity_interest')->value('id');

        $this->patchJson(self::LIST_ENDPOINT.'/'.$id, ['status' => 'won'])->assertUnprocessable();
        $this->patchJson(self::LIST_ENDPOINT.'/'.$id, [])->assertUnprocessable();
        $this->patchJson(self::LIST_ENDPOINT.'/999999', ['status' => 'contacted'])->assertNotFound();
    }

    public function test_update_is_fail_closed_on_rbac(): void
    {
        $this->captureEquityInterest();
        $id = (int) DB::table('marketing_leads')->where('type', 'equity_interest')->value('id');

        $this->patchJson(self::LIST_ENDPOINT.'/'.$id, ['status' => 'contacted'])->assertUnauthorized();

        $this->actingAsPlatformRole(PlatformRole::Support);
        $this->patchJson(self::LIST_ENDPOINT.'/'.$id, ['status' => 'contacted'])->assertForbidden();
    }
}
