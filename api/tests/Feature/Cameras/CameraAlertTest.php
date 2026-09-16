<?php

declare(strict_types=1);

namespace Tests\Feature\Cameras;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchCommunicationJob;
use App\Modules\Cameras\Domain\Models\Camera;
use App\Modules\Cameras\Domain\Models\CameraAlert;
use App\Modules\Cameras\Domain\Models\CameraEvent;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Alertes caméra — issue #7427 (BC-19).
 *
 * Critères d'acceptation couverts :
 *  1. un événement détecté ⇒ **une** ligne d'alerte et **une** notification,
 *     dédoublonnée sur une rafale d'ingestions ;
 *  2. acquittement puis résolution, état cohérent et visible par l'API ;
 *  3. une alerte de sécurité n'est **pas** supprimée par les heures calmes ;
 *  6. aucune alerte sans événement.
 *
 * Le test passe par le vrai schéma tenant (RefreshTenantDatabase) : la
 * migration `2026_09_15_000007_7427_*` est donc exercée, pas contournée.
 */
class CameraAlertTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SECRET = 'mediamtx-secret-test';

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    private Camera $camera;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cameras.mediamtx_secret', self::SECRET);

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['cameras' => true, 'max_cameras' => 4],
        ]);
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['cameras' => true, 'max_cameras' => 4],
        ]);
        $this->otherCompany = $otherCompany;

        $this->manager = $this->managerFor($this->company);

        $this->camera = $this->cameraFor($this->company, 'Entrée principale');
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function managerFor(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function cameraFor(Company $company, string $name): Camera
    {
        /** @var Camera $camera */
        $camera = Camera::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $name,
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $this->manager->id,
            'is_active' => true,
        ]);

        return $camera;
    }

    /**
     * @param  array<string, mixed>  $overrides
     *
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\JsonResponse>
     */
    private function ingest(array $overrides = [], string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/internal/camera-events', array_merge([
            'camera_id' => $this->camera->id,
            'type' => CameraEvent::TYPE_MOTION,
            'severity' => CameraAlert::SEVERITY_WARNING,
            'detected_at' => '2026-09-15T10:00:00+00:00',
        ], $overrides), ['Authorization' => 'Bearer '.$secret]);
    }

    // ── Critère 1 : une alerte (et une notification) par rafale ──────────────

    public function test_une_rafale_d_ingestions_produit_une_seule_alerte_et_une_seule_notification(): void
    {
        Queue::fake();

        for ($i = 0; $i < 10; $i++) {
            $this->ingest()->assertStatus(201);
        }

        // Le journal brut conserve les 10 détections…
        $this->assertSame(10, CameraEvent::withoutGlobalScopes()->count());
        // …mais la rafale ne produit qu'une alerte (dédoublonnage par alert_key).
        $this->assertSame(1, CameraAlert::withoutGlobalScopes()->count());

        Queue::assertPushed(DispatchCommunicationJob::class, 1);
        Queue::assertPushed(
            DispatchCommunicationJob::class,
            fn (DispatchCommunicationJob $job): bool => $job->templateKey === 'camera_security_alert'
                && $job->context['category'] === 'security'
                && $job->context['camera'] === 'Entrée principale'
                && $job->employeeId === (int) $this->manager->id
                && $job->channels === ['app', 'push']
        );

        /** @var CameraAlert $alert */
        $alert = CameraAlert::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(CameraAlert::STATUS_OPEN, $alert->status);
        $this->assertSame($this->camera->id, $alert->camera_id);
        $this->assertNotNull($alert->camera_event_id);
    }

    public function test_deux_fenetres_de_regroupement_distinctes_produisent_deux_alertes(): void
    {
        Queue::fake();

        $this->ingest(['detected_at' => '2026-09-15T10:00:00+00:00'])->assertStatus(201);
        $this->ingest(['detected_at' => '2026-09-15T10:06:00+00:00'])->assertStatus(201);

        $this->assertSame(2, CameraAlert::withoutGlobalScopes()->count());
        Queue::assertPushed(DispatchCommunicationJob::class, 2);
    }

    // ── Critère 3 : les heures calmes ne suppriment pas une alerte sécurité ──

    public function test_une_alerte_de_securite_n_est_pas_supprimee_par_les_heures_calmes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 22:15:00', 'Africa/Algiers'));

        try {
            NotificationPreference::query()->create([
                'company_id' => $this->company->id,
                'employee_id' => $this->manager->id,
                'app_enabled' => true,
                'email_enabled' => true,
                'push_enabled' => true,
                'timezone' => 'Africa/Algiers',
                'categories' => ['security' => true],
                'quiet_hours' => [
                    'enabled' => true,
                    'start' => '20:00',
                    'end' => '07:00',
                ],
            ]);

            // Aucun Queue::fake : la chaîne complète tourne (queue sync) —
            // service → DispatchCommunicationJob → CommunicationService.
            $this->ingest()->assertStatus(201);

            /** @var Notification $notification */
            $notification = Notification::query()->firstOrFail();
            $this->assertSame('security', $notification->type);
            $this->assertStringContainsString('Entrée principale', (string) $notification->body);

            // Le push n'est pas « étouffé » par les heures calmes.
            $this->assertSame(
                0,
                CommunicationEvent::query()->where('error_message', 'Quiet hours active.')->count()
            );

            $pushEvents = CommunicationEvent::query()->where('channel', 'push')->get();
            $this->assertTrue($pushEvents->isNotEmpty());
            $pushEvents->each(function (CommunicationEvent $event): void {
                $this->assertNotSame('skipped', $event->status);
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    // ── Critère 2 : acquittement puis résolution ────────────────────────────

    public function test_acquittement_puis_resolution_etat_coherent(): void
    {
        Queue::fake();

        $this->ingest()->assertStatus(201);
        /** @var CameraAlert $alert */
        $alert = CameraAlert::withoutGlobalScopes()->firstOrFail();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/cameras/alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', CameraAlert::STATUS_ACKNOWLEDGED)
            ->assertJsonPath('data.acknowledged_by', $this->manager->id)
            ->assertJsonPath('data.camera_name', 'Entrée principale');

        $alert->refresh();
        $this->assertSame(CameraAlert::STATUS_ACKNOWLEDGED, $alert->status);
        $this->assertNotNull($alert->acknowledged_at);
        $this->assertNull($alert->resolved_at);

        $this->postJson("/api/v1/cameras/alerts/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', CameraAlert::STATUS_RESOLVED)
            ->assertJsonPath('data.resolved_by', $this->manager->id);

        $alert->refresh();
        $this->assertSame(CameraAlert::STATUS_RESOLVED, $alert->status);
        $this->assertNotNull($alert->resolved_at);

        $this->getJson('/api/v1/cameras/alerts?status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', CameraAlert::STATUS_RESOLVED)
            ->assertJsonPath('data.0.id', $alert->id);

        $this->getJson('/api/v1/cameras/alerts?status=open')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_alerte_d_un_autre_tenant_est_invisible_et_non_acquittable(): void
    {
        Queue::fake();

        $this->ingest()->assertStatus(201);
        /** @var CameraAlert $alert */
        $alert = CameraAlert::withoutGlobalScopes()->firstOrFail();

        Sanctum::actingAs($this->managerFor($this->otherCompany));

        $this->getJson('/api/v1/cameras/alerts')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/cameras/alerts/{$alert->id}/acknowledge")->assertStatus(404);

        $alert->refresh();
        $this->assertSame(CameraAlert::STATUS_OPEN, $alert->status);
    }

    public function test_un_employe_non_manager_n_accede_pas_aux_alertes(): void
    {
        Queue::fake();
        $this->ingest()->assertStatus(201);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/cameras/alerts')->assertStatus(403);
        $this->getJson('/api/v1/cameras/events')->assertStatus(403);
    }

    // ── Critère 6 : aucune alerte sans événement ────────────────────────────

    public function test_aucune_alerte_sans_evenement(): void
    {
        Queue::fake();

        // Sans ingestion, rien n'est créé : le serveur ne fabrique pas de
        // détection et donc pas d'alerte.
        $this->assertSame(0, CameraEvent::withoutGlobalScopes()->count());
        $this->assertSame(0, CameraAlert::withoutGlobalScopes()->count());

        $this->ingest()->assertStatus(201);

        $this->assertSame(1, CameraAlert::withoutGlobalScopes()->count());
        // Structurellement, chaque alerte référence son événement déclencheur.
        $this->assertSame(0, CameraAlert::withoutGlobalScopes()->whereNull('camera_event_id')->count());

        /** @var CameraAlert $alert */
        $alert = CameraAlert::withoutGlobalScopes()->firstOrFail();
        /** @var CameraEvent $event */
        $event = CameraEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($event->id, $alert->camera_event_id);
        $this->assertSame($event->type, $alert->type);
    }

    // ── Sévérité & ingestion ────────────────────────────────────────────────

    public function test_severite_est_propagee_et_defaut_documente_par_type(): void
    {
        Queue::fake();

        $this->ingest(['type' => CameraEvent::TYPE_PERSON, 'severity' => CameraAlert::SEVERITY_CRITICAL])
            ->assertStatus(201);
        // `severity: null` = le détecteur ne classe pas : le défaut documenté
        // du type s'applique (véhicule = info).
        $this->ingest(['type' => CameraEvent::TYPE_VEHICLE, 'severity' => null])
            ->assertStatus(201);

        $byType = CameraAlert::withoutGlobalScopes()->pluck('severity', 'type')->all();

        $this->assertSame(CameraAlert::SEVERITY_CRITICAL, $byType[CameraEvent::TYPE_PERSON]);
        $this->assertSame(CameraAlert::SEVERITY_INFO, $byType[CameraEvent::TYPE_VEHICLE]);
    }

    public function test_l_ingestion_exige_le_secret_partage_mediamtx(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/internal/camera-events', [
            'camera_id' => $this->camera->id,
            'type' => CameraEvent::TYPE_MOTION,
        ])->assertStatus(401)->assertJsonPath('error', 'UNAUTHENTICATED');

        $this->ingest([], 'mauvais-secret')->assertStatus(401);

        $this->assertSame(0, CameraEvent::withoutGlobalScopes()->count());
        $this->assertSame(0, CameraAlert::withoutGlobalScopes()->count());
    }

    public function test_ingestion_refusee_pour_une_camera_inconnue_ou_inactive(): void
    {
        Queue::fake();

        $this->ingest(['camera_id' => 999999])
            ->assertStatus(404)
            ->assertJsonPath('error', 'CAMERA_NOT_FOUND')
            ->assertJsonPath('reason', 'camera_unknown');

        $this->camera->update(['is_active' => false]);

        $this->ingest()
            ->assertStatus(404)
            ->assertJsonPath('reason', 'camera_inactive');

        $this->assertSame(0, CameraEvent::withoutGlobalScopes()->count());
    }

    public function test_journal_des_evenements_ne_divulgue_pas_le_chemin_du_snapshot(): void
    {
        Queue::fake();

        $this->ingest(['snapshot_path' => 'snapshots/2026/09/15/cam-1.jpg'])->assertStatus(201);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/cameras/events?type=motion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.has_snapshot', true)
            ->assertJsonPath('data.0.camera_name', 'Entrée principale');

        $this->assertArrayNotHasKey('snapshot_path', (array) $response->json('data.0'));
    }
}
