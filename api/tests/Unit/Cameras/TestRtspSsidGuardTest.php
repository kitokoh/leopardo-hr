<?php

declare(strict_types=1);

namespace Tests\Unit\Cameras;

use App\Modules\Cameras\Infrastructure\Services\CameraService;
use PHPUnit\Framework\TestCase;

/**
 * QA #3147 — anti-SSRF : les cibles internes (loopback, RFC1918,
 * link-local, CGNAT, réservées) ne doivent jamais être testées par ffprobe.
 * La logique est testée par réflexion (méthodes privées du service).
 */
class TestRtspSsidGuardTest extends TestCase
{
    private function isPrivate(string $url): bool
    {
        $reflection = new \ReflectionClass(CameraService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isPrivateRtspTarget');
        $method->setAccessible(true);

        return (bool) $method->invoke($service, $url);
    }

    /** @return iterable<string, array{0: string}> */
    public static function privateTargets(): iterable
    {
        yield 'loopback IPv4' => ['rtsp://127.0.0.1:8554/stream'];
        yield 'localhost' => ['rtsp://localhost:8554/stream'];
        yield 'RFC1918 10/8' => ['rtsp://10.0.0.5:8554/stream'];
        yield 'RFC1918 172.16/12' => ['rtsp://172.20.10.2:8554/stream'];
        yield 'RFC1918 192.168/16' => ['rtsp://192.168.1.10:8554/stream'];
        yield 'link-local' => ['rtsp://169.254.169.254:8554/stream'];
        yield 'CGNAT' => ['rtsp://100.64.0.1:8554/stream'];
        yield 'TEST-NET' => ['rtsp://192.0.2.1:8554/stream'];
        yield 'multicast' => ['rtsp://239.1.1.1:8554/stream'];
        yield 'loopback IPv6' => ['rtsp://[::1]:8554/stream'];
        yield 'IPv6 ULA' => ['rtsp://[fd00::1]:8554/stream'];
        yield 'IPv6 link-local' => ['rtsp://[fe80::1]:8554/stream'];
        yield 'hôte .internal' => ['rtsp://db.internal:8554/stream'];
        yield 'hôte .local' => ['rtsp://printer.local:8554/stream'];
    }

    /** @return iterable<string, array{0: string}> */
    public static function publicTargets(): iterable
    {
        yield 'IP documentation (TEST-NET-3)' => ['rtsp://203.0.113.10:8554/stream'];
        yield 'hôte public' => ['rtsp://camera.example.com:8554/stream'];
    }

    /**
     * @dataProvider privateTargets
     */
    public function test_private_targets_are_rejected(string $url): void
    {
        $this->assertTrue($this->isPrivate($url), "devrait être bloquée : {$url}");
    }

    /**
     * @dataProvider publicTargets
     */
    public function test_public_targets_are_allowed(string $url): void
    {
        $this->assertFalse($this->isPrivate($url), "ne devrait pas être bloquée : {$url}");
    }
}
