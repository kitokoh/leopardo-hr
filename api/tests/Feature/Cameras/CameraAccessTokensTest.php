<?php

namespace Tests\Feature\Cameras;

use App\Modules\Cameras\Domain\Models\Camera;
use App\Modules\Cameras\Domain\Models\CameraAccessToken;
use App\Modules\Cameras\Domain\Models\CameraPermission;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesCameraFixtures;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CameraAccessTokensTest extends TestCase
{
    use CreatesCameraFixtures;
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config()->set('cameras.default_max_cameras', 4);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_principal_can_issue_and_revoke_access_tokens(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $issue = $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras/'.$cam->id.'/access-tokens', [
                'label' => 'Assureur AXA',
                'granted_to_email' => 'audit@axa.test',
                'granted_to_name' => 'AXA Audit',
                'expires_in_minutes' => 60,
            ]);

        $issue->assertStatus(201);
        $tokenId = (int) $issue->json('data.id');
        $rawToken = $issue->json('data.token');
        $this->assertIsString($rawToken);
        $this->assertSame(64, strlen($rawToken));

        $list = $this->withHeaders($this->authHeaders($principal, 't2'))
            ->getJson('/api/v1/cameras/'.$cam->id.'/access-tokens');
        $list->assertOk();
        $list->assertJsonCount(1, 'data');
        // Le token brut ne doit PAS être retourné en liste.
        $this->assertNull($list->json('data.0.token'));

        $revoke = $this->withHeaders($this->authHeaders($principal, 't3'))
            ->deleteJson('/api/v1/cameras/'.$cam->id.'/access-tokens/'.$tokenId);
        $revoke->assertOk();
        $token = CameraAccessToken::query()->findOrFail($tokenId);
        $this->assertTrue((bool) $token->is_revoked);
    }

    /**
     * #7425 — le lien de partage ne doit JAMAIS transporter le jeton en chaîne
     * de requête : il est journalisé par les proxys/CDN et transmis dans
     * l'en-tête `Referer` (#4931/#6560). Le viewer public n'accepte d'ailleurs
     * plus qu'un jeton en en-tête `X-Token` — un lien `?t=` serait donc à la
     * fois fuyant ET inutilisable.
     */
    public function test_share_url_keeps_the_token_out_of_the_query_string(): void
    {
        config(['cameras.public_view_url' => 'https://app.test/view/cam']);

        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Entrée',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $issue = $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras/'.$cam->id.'/access-tokens', [
                'label' => 'Assureur',
                'expires_in_minutes' => 60,
            ]);

        $issue->assertStatus(201);
        $shareUrl = (string) $issue->json('data.share_url');
        $rawToken = (string) $issue->json('data.token');

        $this->assertStringStartsWith('https://app.test/view/cam#t=', $shareUrl);
        $this->assertStringContainsString($rawToken, $shareUrl);
        // Le point qui compte : aucun paramètre de requête ne porte le jeton.
        $this->assertStringNotContainsString('?t=', $shareUrl);
        $this->assertSame('', (string) parse_url($shareUrl, PHP_URL_QUERY));
    }

    public function test_disallowed_duration_is_rejected(): void
    {
        $company = $this->createCompanyWithCameras();
        $principal = $this->createManager($company);

        $cam = Camera::query()->create([
            'company_id' => $company->id,
            'name' => 'Cam',
            'rtsp_url' => 'rtsp://admin:pass@10.0.0.1:554/live',
            'created_by' => $principal->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras/'.$cam->id.'/access-tokens', [
                'expires_in_minutes' => 7, // hors liste
            ]);

        $response->assertStatus(422);
    }

    public function test_public_viewer_reads_token_payload(): void
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

        $response = $this->withHeader('X-Token', $access->token)->getJson('/api/v1/view/cam');
        $response->assertOk();
        $response->assertJsonPath('data.camera.id', $cam->id);
        $response->assertJsonPath('data.stream_token', $access->token);
    }

    public function test_public_viewer_rejects_revoked_or_expired_token(): void
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
            'expires_at' => Carbon::now('UTC')->subHour(),
        ]);

        $this->withHeader('X-Token', $expired->token)->getJson('/api/v1/view/cam')->assertStatus(404);

        $revoked = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $cam->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'is_revoked' => true,
            'expires_at' => Carbon::now('UTC')->addHour(),
        ]);

        $this->withHeader('X-Token', $revoked->token)->getJson('/api/v1/view/cam')->assertStatus(404);
    }

    public function test_expired_share_permission_cannot_issue_or_revoke_access_tokens(): void
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
            'can_share' => true,
            'granted_by' => $principal->id,
            'expires_at' => Carbon::now('UTC')->subMinute(),
        ]);

        $this->withHeaders($this->authHeaders($supervisor))
            ->postJson('/api/v1/cameras/'.$cam->id.'/access-tokens', [
                'label' => 'Expired permission',
                'expires_in_minutes' => 60,
            ])
            ->assertStatus(403);

        $token = CameraAccessToken::query()->create([
            'company_id' => $company->id,
            'camera_id' => $cam->id,
            'token' => bin2hex(random_bytes(32)),
            'granted_by' => $principal->id,
            'permissions' => ['view' => true],
            'expires_at' => Carbon::now('UTC')->addHour(),
        ]);

        $this->withHeaders($this->authHeaders($supervisor, 'revoke-token'))
            ->deleteJson('/api/v1/cameras/'.$cam->id.'/access-tokens/'.$token->id)
            ->assertStatus(403);
    }
}
