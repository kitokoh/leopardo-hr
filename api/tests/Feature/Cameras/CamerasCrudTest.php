<?php

namespace Tests\Feature\Cameras;

use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraPermission;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesCameraFixtures;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CamerasCrudTest extends TestCase
{
    use CreatesCameraFixtures;
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config()->set('cameras.default_max_cameras', 4);
        config()->set('cameras.test_rtsp.enabled', false);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_principal_can_create_and_list_cameras_with_plan_limit(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $response = $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras', [
                'name' => 'Entrée principale',
                'rtsp_url' => 'rtsp://admin:pass@192.168.1.100:554/stream1',
                'location' => 'Hall RDC',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'stream_url',
                'stream_token',
                'token_expires_at',
            ],
        ]);

        $list = $this->withHeaders($this->authHeaders($principal, 'tests2'))
            ->getJson('/api/v1/cameras');

        $list->assertOk();
        $list->assertJsonCount(1, 'data');
        $list->assertJsonPath('plan_limit.max_cameras', 4);
        $list->assertJsonPath('plan_limit.current_count', 1);
    }

    public function test_rtsp_url_is_encrypted_at_rest(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras', [
                'name' => 'Cam1',
                'rtsp_url' => 'rtsp://user:secret@10.0.0.1:554/live',
            ])->assertStatus(201);

        $raw = \DB::table('cameras')->first();

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('rtsp://', (string) $raw->rtsp_url);
        $this->assertStringNotContainsString('secret', (string) $raw->rtsp_url);

        // Le modèle déchiffre à la lecture.
        $camera = Camera::query()->first();
        $this->assertSame('rtsp://user:secret@10.0.0.1:554/live', $camera->rtsp_url);
    }

    public function test_creation_rejected_when_plan_limit_reached(): void
    {
        $company = $this->createCompanyWithCameras('beta', ['cameras' => true, 'max_cameras' => 1]);
        $principal = $this->createManager($company);

        $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras', [
                'name' => 'C1',
                'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/stream',
            ])->assertStatus(201);

        $response = $this->withHeaders($this->authHeaders($principal, 'tokens2'))
            ->postJson('/api/v1/cameras', [
                'name' => 'C2',
                'rtsp_url' => 'rtsp://admin:pass@10.0.0.2:554/stream',
            ]);

        $response->assertStatus(403);
        $this->assertSame('CAMERA_LIMIT_REACHED', $response->json('error'));
    }

    public function test_feature_flag_disabled_returns_403(): void
    {
        $company = $this->createCompanyWithCameras('gamma', ['cameras' => false]);
        $principal = $this->createManager($company);

        $response = $this->withHeaders($this->authHeaders($principal))
            ->getJson('/api/v1/cameras');

        $response->assertStatus(403);
        $this->assertSame('FEATURE_NOT_ENABLED', $response->json('error'));
    }

    public function test_non_manager_employee_cannot_list(): void
    {
        $company = $this->createCompanyWithCameras();
        $employee = $this->createEmployee($company);

        $response = $this->withHeaders($this->authHeaders($employee))
            ->getJson('/api/v1/cameras');

        $response->assertStatus(403);
    }

    public function test_invalid_rtsp_url_is_rejected(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $response = $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras', [
                'name' => 'Bad',
                'rtsp_url' => 'http://192.168.1.100/stream',
            ]);

        $response->assertStatus(422);
    }

    public function test_principal_can_update_and_soft_delete(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $upd = $this->withHeaders($this->authHeaders($principal))
            ->patchJson('/api/v1/cameras/'.$cam->id, ['name' => 'Cam Renamed']);
        $upd->assertOk();
        $this->assertSame('Cam Renamed', Camera::query()->find($cam->id)->name);

        $del = $this->withHeaders($this->authHeaders($principal, 't2'))
            ->deleteJson('/api/v1/cameras/'.$cam->id);
        $del->assertOk();
        $this->assertNull(Camera::query()->find($cam->id));
        $this->assertNotNull(Camera::withTrashed()->find($cam->id));
    }

    public function test_rh_cannot_create_but_can_view(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company, 'principal', 'p@co.test');
        $rh = $this->createManager($company, 'rh', 'rh@co.test');

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $rhList = $this->withHeaders($this->authHeaders($rh))
            ->getJson('/api/v1/cameras');
        $rhList->assertOk();
        $rhList->assertJsonCount(1, 'data');

        $rhCreate = $this->withHeaders($this->authHeaders($rh, 't2'))
            ->postJson('/api/v1/cameras', [
                'name' => 'X',
                'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            ]);
        $rhCreate->assertStatus(403);
    }

    public function test_expired_manage_permission_cannot_update_camera(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company, 'principal', 'principal@co.test');
        $supervisor = $this->createManager($company, 'superviseur', 'supervisor@co.test');

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        CameraPermission::query()->create([
            'company_id' => $company->id,
            'camera_id' => $cam->id,
            'employee_id' => $supervisor->id,
            'can_view' => true,
            'can_manage' => true,
            'granted_by' => $principal->id,
            'expires_at' => Carbon::now('UTC')->subMinute(),
        ]);

        $this->withHeaders($this->authHeaders($supervisor))
            ->patchJson('/api/v1/cameras/'.$cam->id, ['name' => 'Renamed'])
            ->assertStatus(403);
    }
}
