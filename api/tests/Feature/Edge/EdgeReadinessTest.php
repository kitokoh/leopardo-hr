<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use Tests\TestCase;

/**
 * #4411 — readiness edge : le liveness (/edge/health) reste sans DB (mode
 * autonome offline), mais le readiness doit refléter l'état réel du schéma
 * SQLite local (avant : un nœud frais répondait « ok » avec une sync morte).
 */
class EdgeReadinessTest extends TestCase
{
    public function test_health_stays_db_free(): void
    {
        $response = $this->get('/api/v1/edge/health');

        $response->assertOk()
            ->assertJsonPath('edge', true)
            ->assertJsonPath('status', 'ok');
    }

    public function test_readiness_reports_schema_state_without_crashing(): void
    {
        $response = $this->get('/api/v1/edge/readiness');

        // Contrat : réponse structurée, jamais 500 — ok si le schéma existe,
        // 503 + reason si le schéma SQLite local est absent (nouveau nœud).
        $response->assertJsonPath('edge', true);

        $status = $response->json('status');
        $this->assertContains($status, ['ok', 'not_ready'], 'status doit être ok ou not_ready');

        if ($status === 'not_ready') {
            $response->assertStatus(503)
                ->assertJsonPath('reason', 'edge_schema_missing');
        } else {
            $response->assertOk()
                ->assertJsonPath('schema', 'provisioned');
        }
    }
}
