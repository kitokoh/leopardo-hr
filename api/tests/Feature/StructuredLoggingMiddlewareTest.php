<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class StructuredLoggingMiddlewareTest extends TestCase
{
    public function test_api_requests_are_logged_to_structured_channel(): void
    {
        // /api/v1/metrics est protégé par auth:super_admin_api (issue #1466) :
        // le test doit s'authentifier pour obtenir un 200 et vérifier le log
        // structuré du middleware (route anonyme → 401 → le handler d'exception
        // logge une erreur non attendue par le mock, cf. CI rouge 2026-08-09).
        Sanctum::actingAs(
            new SuperAdmin(['id' => 1, 'name' => 'Audit', 'email' => 'audit@leopardo.test']),
            ['*'],
            'super_admin_api'
        );

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('http_request', Mockery::on(function (array $context): bool {
                return $context['method'] === 'GET'
                    && $context['uri'] === '/api/v1/metrics'
                    && $context['status'] === 200
                    && is_int($context['duration_ms'])
                    && $context['request_id'] === 'structured-test-request';
            }));

        Log::shouldReceive('channel')
            ->once()
            ->with('structured')
            ->andReturn($logger);

        $response = $this->getJson('/api/v1/metrics', [
            'X-Request-Id' => 'structured-test-request',
        ]);

        $response->assertOk();
    }

    public function test_health_requests_are_not_written_to_structured_log(): void
    {
        Log::shouldReceive('channel')->never();

        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk();
    }
}
