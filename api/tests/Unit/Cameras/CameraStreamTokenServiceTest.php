<?php

declare(strict_types=1);

namespace Tests\Unit\Cameras;

use App\Modules\Cameras\Infrastructure\Streaming\CameraStreamTokenService;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

/**
 * #6560 (audit sécurité F1) — le secret de signature des stream tokens
 * caméra ne doit JAMAIS être dérivé d'APP_KEY hors local/testing : sans
 * CAMERAS_STREAM_TOKEN_SECRET dédié en production, l'émission de token doit
 * échouer (fail-closed), pas se replier silencieusement.
 */
class CameraStreamTokenServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restaure l'environnement de test pour les tests suivants du process.
        $this->app->detectEnvironment(fn () => 'testing');
        parent::tearDown();
    }

    public function test_dedicated_secret_is_used_when_configured(): void
    {
        Config::set('cameras.stream_token.secret', 'dedicated-secret-value');

        $service = new CameraStreamTokenService;

        $this->assertSame('dedicated-secret-value', $service->secret());
    }

    public function test_production_without_dedicated_secret_fails_closed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('cameras.stream_token.secret', null);

        $service = new CameraStreamTokenService;

        $this->expectException(RuntimeException::class);
        // #6560 : en production, le message explicite est « is required in production ».
        $this->expectExceptionMessage('CAMERAS_STREAM_TOKEN_SECRET is required in production');

        $service->secret();
    }

    public function test_local_without_dedicated_secret_keeps_dev_fallback(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        Config::set('cameras.stream_token.secret', null);
        Config::set('app.key', 'base64:'.base64_encode('0123456789abcdef0123456789abcdef'));

        $service = new CameraStreamTokenService;

        $this->assertSame(hash('sha256', 'base64:'.base64_encode('0123456789abcdef0123456789abcdef')), $service->secret());
    }
}
