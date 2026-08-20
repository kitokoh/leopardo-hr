<?php

namespace Tests\Feature\Cameras;

use Tests\TestCase;
use Tests\Support\CreatesCameraFixtures;
use Tests\Support\CreatesMvpSchema;

/**
 * Issue #3147 — SSRF : POST /cameras/test-rtsp ne doit jamais sonder le
 * réseau interne (loopback, RFC1918, link-local, résolution DNS privée).
 */
class CameraRtspSecurityTest extends TestCase
{
    use CreatesCameraFixtures;
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config()->set('cameras.test_rtsp.enabled', true);
        config()->set('cameras.test_rtsp.binary', 'ffprobe');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function assertHostRejected(string $rtspUrl): void
    {
        $suffix = substr(md5($rtspUrl), 0, 8);
        $company = $this->createCompanyWithCameras('alpha-'.$suffix);
        $principal = $this->createManager($company, 'principal', 'manager-'.$suffix.'@company.test');

        $response = $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras/test-rtsp', ['rtsp_url' => $rtspUrl]);

        $response->assertOk();
        $this->assertSame('host_not_allowed', $response->json('error'));
        $this->assertFalse($response->json('ok'));
    }

    public function test_loopback_is_rejected(): void
    {
        $this->assertHostRejected('rtsp://127.0.0.1:554/stream');
        $this->assertHostRejected('rtsp://localhost:554/stream');
    }

    public function test_private_ranges_are_rejected(): void
    {
        $this->assertHostRejected('rtsp://192.168.1.10:554/stream');
        $this->assertHostRejected('rtsp://10.0.0.5:554/stream');
        $this->assertHostRejected('rtsp://172.16.0.1:554/stream');
        $this->assertHostRejected('rtsp://169.254.169.254:554/latest/meta-data');
    }

    public function test_invalid_scheme_is_rejected_without_shell(): void
    {
        $company = $this->createCompanyWithCameras('alpha-http');
        $principal = $this->createManager($company, 'principal', 'manager-http@company.test');

        $this->withHeaders($this->authHeaders($principal))
            ->postJson('/api/v1/cameras/test-rtsp', ['rtsp_url' => 'http://192.168.1.1/x'])
            ->assertOk()
            ->assertJsonPath('error', 'invalid_url');
    }
}
