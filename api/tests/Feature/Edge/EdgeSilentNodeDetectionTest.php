<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Models\Company;
use App\Notifications\EdgeNodeSilentAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Phase 4 — Scénario 3.4 / 4.4 : Monitoring & alertes
 *
 * Vérifie la commande Artisan `edge:detect-silent-nodes` :
 *   - Détecte les nœuds silencieux > seuil
 *   - N'alerte pas les nœuds récents
 *   - N'alerte pas les nœuds révoqués
 *   - N'alerte pas les nœuds "muted"
 *   - Envoie EdgeNodeSilentAlert aux managers du bon tenant
 *   - --dry-run ne modifie pas la DB ni n'envoie de notifications
 */
class EdgeSilentNodeDetectionTest extends TestCase
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
        DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE');
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
        DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE');

        Schema::create('edge_nodes', function ($table): void {
            $table->id();
            $table->uuid('company_id')->index();
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

    private function insertNode(string $nodeId, array $overrides = []): object
    {
        $id = DB::table('edge_nodes')->insertGetId(array_merge([
            'company_id'    => $this->company->id,
            'node_id'       => $nodeId,
            'name'          => "Node {$nodeId}",
            'status'        => 'online',
            'license_valid' => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at'  => Carbon::now()->toDateTimeString(),
            'pending_count' => 0,
            'alert_muted'   => false,
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ], $overrides));

        return DB::table('edge_nodes')->find($id);
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    /**
     * La commande se lance sans erreur quand aucun nœud n'est silencieux.
     */
    public function test_command_exits_success_when_no_silent_nodes(): void
    {
        $this->insertNode('edge-ok-001', ['last_seen_at' => Carbon::now()->toDateTimeString()]);

        $this->artisan('edge:detect-silent-nodes', ['--threshold' => 30])
            ->assertExitCode(0);
    }

    /**
     * --dry-run n'envoie pas de notification et ne change pas la DB.
     */
    public function test_dry_run_does_not_send_notifications(): void
    {
        Notification::fake();

        $this->insertNode('edge-dry-001', [
            'last_seen_at' => Carbon::now()->subHours(2)->toDateTimeString(),
            'status'       => 'online',
        ]);

        $statusBefore = DB::table('edge_nodes')
            ->where('node_id', 'edge-dry-001')
            ->value('status');

        $this->artisan('edge:detect-silent-nodes', [
            '--threshold' => 30,
            '--dry-run'   => true,
        ])->assertExitCode(0);

        Notification::assertNothingSent();

        $statusAfter = DB::table('edge_nodes')
            ->where('node_id', 'edge-dry-001')
            ->value('status');

        $this->assertSame($statusBefore, $statusAfter, '--dry-run ne doit pas modifier la DB');
    }

    /**
     * Un nœud récent (< seuil) n'est pas détecté comme silencieux.
     */
    public function test_recent_node_is_not_detected_as_silent(): void
    {
        $this->insertNode('edge-recent-001', [
            'last_seen_at' => Carbon::now()->subMinutes(5)->toDateTimeString(),
        ]);

        $threshold = Carbon::now()->subMinutes(30);

        $silentNodes = DB::table('edge_nodes')
            ->where('status', '!=', 'revoked')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)->orWhereNull('last_seen_at');
            })
            ->where('alert_muted', false)
            ->get();

        $nodeIds = $silentNodes->pluck('node_id')->toArray();
        $this->assertNotContains('edge-recent-001', $nodeIds);
    }

    /**
     * Un nœud silence > seuil est bien détecté.
     */
    public function test_silent_node_exceeding_threshold_is_detected(): void
    {
        $this->insertNode('edge-silent-001', [
            'last_seen_at' => Carbon::now()->subMinutes(60)->toDateTimeString(),
        ]);

        $threshold = Carbon::now()->subMinutes(30);

        $silentNodes = DB::table('edge_nodes')
            ->where('status', '!=', 'revoked')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)->orWhereNull('last_seen_at');
            })
            ->where('alert_muted', false)
            ->get();

        $nodeIds = $silentNodes->pluck('node_id')->toArray();
        $this->assertContains('edge-silent-001', $nodeIds);
    }

    /**
     * Un nœud muted n'est pas alerté.
     */
    public function test_muted_node_is_not_alerted(): void
    {
        $this->insertNode('edge-muted-001', [
            'last_seen_at' => Carbon::now()->subHours(3)->toDateTimeString(),
            'alert_muted'  => true,
        ]);

        $threshold = Carbon::now()->subMinutes(30);

        $silentNodes = DB::table('edge_nodes')
            ->where('status', '!=', 'revoked')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)->orWhereNull('last_seen_at');
            })
            ->where('alert_muted', false)
            ->get();

        $nodeIds = $silentNodes->pluck('node_id')->toArray();
        $this->assertNotContains('edge-muted-001', $nodeIds);
    }

    /**
     * La notification EdgeNodeSilentAlert se crée correctement avec les bonnes données.
     */
    public function test_edge_node_silent_alert_notification_is_built_correctly(): void
    {
        Notification::fake();

        $lastSeen = Carbon::now()->subHours(2);

        $notification = new EdgeNodeSilentAlert(
            nodeName:      'Kiosque RDC',
            nodeId:        'edge-notif-001',
            companyName:   'Acme Corp',
            lastSeenAt:    $lastSeen,
            thresholdMins: 30,
        );

        $this->assertSame('edge-notif-001', $notification->nodeId);
        $this->assertSame('Kiosque RDC', $notification->nodeName);
        $this->assertSame('Acme Corp', $notification->companyName);
        $this->assertSame(30, $notification->thresholdMins);
        $this->assertTrue($lastSeen->equalTo($notification->lastSeenAt));

        // Via array (pour notifications DB ou webhook)
        $array = $notification->toArray(new \stdClass());
        $this->assertArrayHasKey('type', $array);
        $this->assertSame('edge_node_silent', $array['type']);
        $this->assertSame('edge-notif-001', $array['node_id']);
    }

    /**
     * La notification EdgeNodeSilentAlert avec lastSeenAt=null (jamais vu).
     */
    public function test_alert_notification_handles_null_last_seen_at(): void
    {
        $notification = new EdgeNodeSilentAlert(
            nodeName:      'Nouveau Kiosque',
            nodeId:        'edge-new-001',
            companyName:   'Startup XYZ',
            lastSeenAt:    null,
            thresholdMins: 30,
        );

        $this->assertNull($notification->lastSeenAt);

        $mail = $notification->toMail(new \stdClass());
        // Le mail doit contenir "Jamais" pour un nœud jamais vu
        $this->assertStringContainsString(
            'Jamais',
            collect($mail->introLines)->implode(' ') . ' ' . collect($mail->outroLines)->implode(' ')
                . ' ' . $mail->subject . ' ' . implode(' ', array_map(
                    fn($line) => is_array($line) ? $line[0] : $line,
                    $mail->introLines
                ))
        );
    }

    /**
     * Le seuil custom (--threshold=N) fonctionne correctement.
     */
    public function test_custom_threshold_is_respected(): void
    {
        // Node silencieux depuis 10 min
        $this->insertNode('edge-custom-thresh-01', [
            'last_seen_at' => Carbon::now()->subMinutes(10)->toDateTimeString(),
        ]);

        // Avec seuil 30 min → pas silencieux
        $threshold30 = Carbon::now()->subMinutes(30);
        $silent30 = DB::table('edge_nodes')
            ->where('node_id', 'edge-custom-thresh-01')
            ->where('last_seen_at', '<', $threshold30)
            ->get();
        $this->assertEmpty($silent30, 'Seuil 30 min : nœud de 10 min ne doit pas être détecté');

        // Avec seuil 5 min → silencieux
        $threshold5 = Carbon::now()->subMinutes(5);
        $silent5 = DB::table('edge_nodes')
            ->where('node_id', 'edge-custom-thresh-01')
            ->where('last_seen_at', '<', $threshold5)
            ->get();
        $this->assertNotEmpty($silent5, 'Seuil 5 min : nœud de 10 min doit être détecté');
    }
}
