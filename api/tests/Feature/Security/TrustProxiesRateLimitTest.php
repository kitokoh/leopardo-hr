<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #4494 — trustProxies('*') permettait à n'importe quel client de forger
 * X-Forwarded-For et de changer d'IP à chaque requête, contournant les
 * limiteurs IP. Le middleware ne fait plus confiance qu'aux réseaux privés
 * (loopback + RFC1918 + ULA) : un peer public voit XFF ignoré.
 *
 * @see api/bootstrap/app.php — $middleware->trustProxies(...)
 */
class TrustProxiesRateLimitTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['security.rate_limits.auth_per_minute' => 2]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * Un client direct (peer IP publique) ne peut pas contourner le rate-limit
     * en forgeant X-Forwarded-For : toutes ses requêtes partagent le même
     * bucket (clé = IP réelle), donc le 3ᵉ appel reçoit 429 malgré des XFF
     * différents.
     */
    public function test_forged_xff_from_public_peer_does_not_bypass_ip_rate_limit(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

        $payload = [
            'email' => 'xff-forged@example.test',
            'password' => 'wrong-password',
            'device_name' => 'test-suite',
        ];

        $this->withHeader('X-Forwarded-For', '198.51.100.1');
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);

        $this->withHeader('X-Forwarded-For', '198.51.100.2');
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);

        // Bucket unique sur 203.0.113.10 → 3ᵉ requête = 429, même avec un
        // 3ᵉ XFF forgé différent.
        $this->withHeader('X-Forwarded-For', '198.51.100.3');
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    /**
     * Depuis un proxy de confiance (peer RFC1918 — cas Render), X-Forwarded-For
     * reste honoré : l'IP réelle du client est bien la clé du bucket (les XFF
     * différents consomment des buckets distincts → pas de 429 prématuré).
     */
    public function test_xff_from_trusted_private_proxy_is_still_honored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5']);

        $payload = [
            'email' => 'xff-trusted@example.test',
            'password' => 'wrong-password',
            'device_name' => 'test-suite',
        ];

        $this->withHeader('X-Forwarded-For', '198.51.100.10');
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);

        // Nouvelle IP client via le proxy de confiance → nouveau bucket → 401.
        $this->withHeader('X-Forwarded-For', '198.51.100.11');
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
    }
}
