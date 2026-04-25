<?php

namespace Tests\Feature\Cameras;

use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraAccessToken;
use App\Services\Cameras\CameraStreamTokenService;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesCameraFixtures;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Vérifie l'endpoint interne /internal/camera-token/verify consommé par MediaMTX.
 */
class CameraStreamTokenVerifyTest extends TestCase
{
    use CreatesCameraFixtures;
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_verify_accepts_valid_stream_token_jwt(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        /** @var CameraStreamTokenService $service */
        $service = app(CameraStreamTokenService::class);
        $issued = $service->issue($cam->fresh(), $principal->id);

        $response = $this->getJson('/api/v1/internal/camera-token/verify?token='.urlencode($issued['token']).'&camera_id='.$cam->id.'&client_ip=1.2.3.4');

        $response->assertOk();
        $response->assertJsonPath('allowed', true);
        $response->assertJsonPath('type', 'stream_token');
    }

    public function test_verify_rejects_tampered_jwt(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        /** @var CameraStreamTokenService $service */
        $service = app(CameraStreamTokenService::class);
        $issued = $service->issue($cam->fresh(), $principal->id);

        $tampered = $issued['token'].'xx';
        $response = $this->getJson('/api/v1/internal/camera-token/verify?token='.urlencode($tampered).'&camera_id='.$cam->id);

        $response->assertOk();
        $response->assertJsonPath('allowed', false);
    }

    public function test_verify_accepts_opaque_access_token_and_increments_use_count(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $access = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $cam->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'expires_at' => Carbon::now('UTC')->addHour(),
        ]);

        $response = $this->getJson('/api/v1/internal/camera-token/verify?token='.$access->token.'&camera_id='.$cam->id);

        $response->assertOk();
        $response->assertJsonPath('allowed', true);
        $response->assertJsonPath('type', 'access_token');

        $fresh = CameraAccessToken::query()->find($access->id);
        $this->assertSame(1, (int) $fresh->use_count);
        $this->assertNotNull($fresh->last_used_at);
    }

    public function test_verify_returns_token_expired_for_expired_access_token(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $expired = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $cam->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'expires_at' => Carbon::now('UTC')->subMinute(),
        ]);

        $response = $this->getJson('/api/v1/internal/camera-token/verify?token='.$expired->token.'&camera_id='.$cam->id);

        $response->assertOk();
        $response->assertJsonPath('allowed', false);
        $response->assertJsonPath('reason', 'token_expired');
    }

    public function test_verify_denies_when_company_suspended(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $access = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $cam->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'expires_at' => Carbon::now('UTC')->addHour(),
        ]);

        $company->status = 'suspended';
        $company->save();

        $response = $this->getJson('/api/v1/internal/camera-token/verify?token='.$access->token.'&camera_id='.$cam->id);
        $response->assertOk();
        $response->assertJsonPath('allowed', false);
        $response->assertJsonPath('reason', 'company_suspended');
    }

    public function test_verify_denies_soft_deleted_or_inactive_camera(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $inactive = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Inactive Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
            'is_active' => false,
        ]);

        $inactiveToken = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $inactive->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'expires_at' => Carbon::now('UTC')->addHour(),
        ]);

        $this->getJson('/api/v1/internal/camera-token/verify?token='.$inactiveToken->token.'&camera_id='.$inactive->id)
            ->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'camera_not_found');

        $deleted = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Deleted Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $deletedToken = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $deleted->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'expires_at' => Carbon::now('UTC')->addHour(),
        ]);

        $deleted->delete();

        $this->getJson('/api/v1/internal/camera-token/verify?token='.$deletedToken->token.'&camera_id='.$deleted->id)
            ->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'camera_not_found');
    }

    public function test_verify_requires_bearer_secret_when_configured(): void
    {
        config()->set('cameras.mediamtx_secret', 'topsecret');

        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $this->getJson('/api/v1/internal/camera-token/verify?token=x&camera_id='.$cam->id)
            ->assertStatus(401);

        $this->getJson('/api/v1/internal/camera-token/verify?token=x&camera_id='.$cam->id, [
            'Authorization' => 'Bearer topsecret',
        ])->assertOk()->assertJsonPath('allowed', false);
    }
}
