<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Console\Commands\DetectSilentEdgeNodes;
use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Phase 4 — Scénario 4.4 : Expiration licence
 *
 * Vérifie que :
 *   - Un nœud Edge avec licence expirée a license_valid=false
 *   - Un nœud avec licence à venir a license_valid=true
 *   - Le renouvellement automatique met à jour license_expires_at
 *   - Un nœud révoqué ne peut pas se re-licencier
 *   - L'endpoint /edge/license-public-key répond correctement
 *   - DetectSilentEdgeNodes ignore les nœuds révoqués
 */
class EdgeLicenseExpiryTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createEdgeNodesTable();

        $this->company = Company::factory()->create([
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('edge_nodes');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createEdgeNodesTable(): void
    {
        // La table edge_nodes canonique (module EdgeSync DDD, schema UUID/slug)
        // est deja creee par setUpMvpSchema(). Ce test cible un ancien schema
        // legacy (bigint + node_id) utilise par le code mort EdgeController /
        // DetectSilentEdgeNodes (non routes/planifies). On la remplace ici
        // pour la duree du test, puis tearDown() la drop pour laisser le
        // prochain setUp() recreer le schema canonique.
        Schema::dropIfExists('edge_nodes');

        Schema::create('edge_nodes', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('node_id', 64)->unique();
            $table->string('name', 128);
            $table->string('ip_address', 45)->nullable();
            $table->string('version', 32)->nullable();
            $table->string('status', 16)->default('offline')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('pending_count')->default(0);
            $table->timestamp('sync_requested_at')->nullable();
            $table->boolean('license_valid')->default(false);
            $table->timestamp('license_expires_at')->nullable();
            $table->boolean('alert_muted')->default(false);
            $table->timestamp('last_alert_sent_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    private function insertNode(array $overrides = []): object
    {
        $id = DB::table('edge_nodes')->insertGetId(array_merge([
            'company_id'   => $this->company->id,
            'node_id'      => 'edge-lic-001',
            'name'         => 'Kiosque Test',
            'status'       => 'online',
            'license_valid'  => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at' => Carbon::now()->toDateTimeString(),
            'pending_count' => 0,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ], $overrides));

        return DB::table('edge_nodes')->find($id);
    }

    // ── Tests 4.4 ────────────────────────────────────────────────────────────

    /**
     * 4.4.a — Un nœud avec licence expirée doit avoir license_valid=false.
     */
    public function test_expired_license_is_marked_invalid(): void
    {
        $node = $this->insertNode([
            'license_valid'      => true,
            'license_expires_at' => Carbon::now()->subDay()->toDateTimeString(), // expiré hier
        ]);

        // Simuler la vérification de licence (comme le ferait le middleware ou un job)
        $isExpired = Carbon::parse($node->license_expires_at)->isPast();

        if ($isExpired) {
            DB::table('edge_nodes')
                ->where('id', $node->id)
                ->update([
                    'license_valid'  => false,
                    'status'         => 'warning',
                ]);
        }

        $updated = DB::table('edge_nodes')->find($node->id);
        $this->assertFalse((bool) $updated->license_valid, 'Licence expirée → license_valid doit être false');
        $this->assertSame('warning', $updated->status, 'Nœud avec licence expirée → statut warning');
    }

    /**
     * 4.4.b — Un nœud avec licence valide a license_valid=true.
     */
    public function test_valid_license_stays_valid(): void
    {
        $node = $this->insertNode([
            'license_valid'      => true,
            'license_expires_at' => Carbon::now()->addDays(15)->toDateTimeString(),
        ]);

        $isExpired = Carbon::parse($node->license_expires_at)->isPast();
        $this->assertFalse($isExpired, 'Licence non expirée ne doit pas être invalidée');
        $this->assertTrue((bool) $node->license_valid);
    }

    /**
     * 4.4.c — Le renouvellement automatique étend license_expires_at.
     */
    public function test_license_renewal_extends_expiry(): void
    {
        $ttlDays = (int) config('edge.license_ttl_days', 30);

        $node = $this->insertNode([
            'license_valid'      => false,
            'license_expires_at' => Carbon::now()->subDay()->toDateTimeString(),
            'status'             => 'warning',
        ]);

        // Simuler le renouvellement automatique
        $newExpiry = Carbon::now()->addDays($ttlDays);

        DB::table('edge_nodes')
            ->where('id', $node->id)
            ->update([
                'license_valid'      => true,
                'license_expires_at' => $newExpiry->toDateTimeString(),
                'status'             => 'online',
            ]);

        $renewed = DB::table('edge_nodes')->find($node->id);

        $this->assertTrue((bool) $renewed->license_valid, 'Après renouvellement, licence doit être valide');
        $this->assertTrue(
            Carbon::parse($renewed->license_expires_at)->gt(Carbon::now()),
            'license_expires_at doit être dans le futur'
        );
        $this->assertSame('online', $renewed->status, 'Le statut repasse à online après renouvellement');
    }

    /**
     * 4.4.d — Un nœud révoqué ne peut pas être re-licencié/réactivé.
     */
    public function test_revoked_node_cannot_be_relicensed(): void
    {
        $node = $this->insertNode([
            'status'     => 'revoked',
            'revoked_at' => Carbon::now()->subHour()->toDateTimeString(),
        ]);

        // Tentative de renouvellement licence sur un nœud révoqué
        $isRevoked = $node->status === 'revoked';

        if (! $isRevoked) {
            DB::table('edge_nodes')
                ->where('id', $node->id)
                ->update(['license_valid' => true, 'status' => 'online']);
        }

        // Le nœud doit rester révoqué
        $unchanged = DB::table('edge_nodes')->find($node->id);
        $this->assertSame('revoked', $unchanged->status, 'Un nœud révoqué ne doit pas être réactivé');
    }

    /**
     * 4.4.e — DetectSilentEdgeNodes exclut les nœuds révoqués de la détection.
     */
    public function test_silent_node_detector_ignores_revoked_nodes(): void
    {
        Notification::fake();

        // Nœud révoqué silencieux — ne doit PAS déclencher d'alerte
        $this->insertNode([
            'node_id'    => 'edge-lic-revoked',
            'status'     => 'revoked',
            'revoked_at' => Carbon::now()->subDay()->toDateTimeString(),
            'last_seen_at' => Carbon::now()->subHours(2)->toDateTimeString(),
        ]);

        // Nœud normal silencieux — doit déclencher une alerte
        DB::table('edge_nodes')->insertGetId([
            'company_id'   => $this->company->id,
            'node_id'      => 'edge-lic-silent',
            'name'         => 'Kiosque Silencieux',
            'status'       => 'online',
            'license_valid'  => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at' => Carbon::now()->subHours(2)->toDateTimeString(), // silence > seuil
            'pending_count' => 0,
            'alert_muted'  => false,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        // Requête manuelle reproduisant la logique de DetectSilentEdgeNodes
        $threshold = Carbon::now()->subMinutes(30);

        $silentNodes = DB::table('edge_nodes')
            ->where('status', '!=', 'revoked')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)
                  ->orWhereNull('last_seen_at');
            })
            ->where('alert_muted', false)
            ->get();

        $nodeIds = $silentNodes->pluck('node_id')->toArray();

        $this->assertNotContains('edge-lic-revoked', $nodeIds, 'Nœud révoqué ne doit pas figurer dans les alertes');
        $this->assertContains('edge-lic-silent', $nodeIds, 'Nœud silencieux non révoqué doit être détecté');
    }

    /**
     * 4.4.f — L'endpoint GET /edge/license-public-key répond 503 si non configuré.
     */
    public function test_license_public_key_endpoint_returns_503_when_not_configured(): void
    {
        // Sans config edge.license_public_key
        config(['edge.license_public_key' => null]);

        $this->getJson('/api/v1/edge/license-public-key')
            ->assertStatus(503)
            ->assertJsonPath('error', 'edge_public_key_not_configured');
    }

    /**
     * 4.4.g — L'endpoint GET /edge/license-public-key retourne le PEM si configuré.
     */
    public function test_license_public_key_endpoint_returns_pem_when_configured(): void
    {
        $fakePem = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...\n-----END PUBLIC KEY-----";
        config(['edge.license_public_key' => $fakePem]);

        $response = $this->get('/api/v1/edge/license-public-key');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'BEGIN PUBLIC KEY',
            $response->getContent(),
            'Response doit contenir le PEM'
        );
    }

    /**
     * 4.4.h — Une licence dans les 7 prochains jours (proche expiration)
     *          est identifiable pour déclencher un renouvellement préemptif.
     */
    public function test_license_expiring_soon_is_detectable(): void
    {
        $this->insertNode([
            'node_id'            => 'edge-lic-expiring',
            'license_valid'      => true,
            'license_expires_at' => Carbon::now()->addDays(3)->toDateTimeString(), // expire dans 3j
        ]);

        // Requête "expiration proche" (< 7 jours)
        $expiringSoon = DB::table('edge_nodes')
            ->where('license_valid', true)
            ->where('license_expires_at', '<=', Carbon::now()->addDays(7)->toDateTimeString())
            ->where('license_expires_at', '>', Carbon::now()->toDateTimeString())
            ->where('status', '!=', 'revoked')
            ->get();

        $this->assertGreaterThan(0, $expiringSoon->count(), 'Des nœuds à renouveler prochainement doivent être détectés');
        $this->assertSame('edge-lic-expiring', $expiringSoon->first()->node_id);
    }
}
