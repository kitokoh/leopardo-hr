<?php

namespace Tests\Feature\Cameras;

use App\Modules\Cameras\Infrastructure\Services\CameraService;
use App\Modules\Cameras\Infrastructure\Streaming\CameraStreamTokenService;
use Tests\TestCase;

/**
 * #7424 (volet 3) — le provider du module Cameras était un stub vide.
 *
 * Ces tests ne vérifient pas un comportement métier (les 22 tests du module le
 * font déjà) : ils verrouillent le fait que le module **déclare** désormais son
 * infrastructure, pour qu'un retour au stub soit détecté.
 */
class CamerasModuleBindingsTest extends TestCase
{
    public function test_the_stream_token_service_is_registered_as_a_singleton(): void
    {
        $first = app(CameraStreamTokenService::class);
        $second = app(CameraStreamTokenService::class);

        $this->assertInstanceOf(CameraStreamTokenService::class, $first);
        $this->assertSame($first, $second, 'CameraStreamTokenService doit être un singleton.');
    }

    public function test_the_camera_service_is_registered_as_a_singleton(): void
    {
        $first = app(CameraService::class);
        $second = app(CameraService::class);

        $this->assertInstanceOf(CameraService::class, $first);
        $this->assertSame($first, $second, 'CameraService doit être un singleton.');
    }

    public function test_the_video_chain_configuration_is_readable(): void
    {
        // La configuration que consomme la chaîne vidéo versionnée dans
        // edge/mediamtx/ doit rester lisible depuis le code.
        $config = config('cameras');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('stream_token', $config);
        $this->assertArrayHasKey('mediamtx_secret', $config);
        $this->assertArrayHasKey('stream_base_url', $config);
    }
}
