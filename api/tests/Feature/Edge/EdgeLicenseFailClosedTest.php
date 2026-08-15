<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Modules\EdgeSync\Application\Services\EdgeLicenseService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Issue #3317 — Licence Edge fail-open : POST /api/v1/edge-node/validate-license
 * acceptait tout payload non signé quand EDGE_LICENSE_PUBLIC_KEY était absent
 * (défaut de config/edge.php).
 *
 * Vérifie que :
 *   - En environnement production, sans clé publique → 422 (fail-closed).
 *   - En production, un payload HS256 forgé (fallback dev) → 422.
 *   - Avec une clé publique configurée, un token invalide → 422.
 */
class EdgeLicenseFailClosedTest extends TestCase
{
    public function test_validate_license_is_fail_closed_in_production_without_public_key(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        Config::set('edge.license_public_key', null);

        // Payload « valide » forgé à la main (aucune signature réelle).
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $body = base64_encode(json_encode([
            'iss'          => 'https://attacker.example',
            'edge_node_id' => '00000000-0000-0000-0000-000000000000',
            'exp'          => now()->addDays(30)->timestamp,
        ]));

        $this->postJson('/api/v1/edge-node/validate-license', [
            'signed_payload' => "$header.$body.",
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);
    }

    public function test_validate_license_rejects_dev_fallback_token_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        Config::set('edge.license_public_key', null);

        // Token HS256 signé avec APP_KEY (fallback dev de sign()) : en
        // production, sans clé publique, il doit être refusé.
        $service = app(EdgeLicenseService::class);
        $signed = $this->signWithAppKey(['exp' => now()->addDays(30)->timestamp]);

        $response = $this->postJson('/api/v1/edge-node/validate-license', [
            'signed_payload' => $signed,
        ]);

        $response->assertStatus(422);
        $this->assertNotTrue($response->json('valid'));
    }

    public function test_validate_license_rejects_garbage_in_production_with_public_key(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        Config::set('edge.license_public_key', "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...\n-----END PUBLIC KEY-----");

        $this->postJson('/api/v1/edge-node/validate-license', [
            'signed_payload' => 'not-a-jwt',
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);
    }

    private function signWithAppKey(array $payload): string
    {
        $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = base64url_encode(json_encode($payload));
        $sig = base64url_encode(hash_hmac('sha256', "$header.$body", (string) config('app.key'), true));

        return "$header.$body.$sig";
    }
}

if (! function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
