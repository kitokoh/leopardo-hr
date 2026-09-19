<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Issue #3591 / #3770 — les assets d'installation Edge (install.sh,
 * docker-compose.yml, Caddyfile.edge) étaient servis depuis
 * `base_path('edge/…')` = `api/edge/…` qui n'existe pas (les fichiers vivent
 * à la racine du monorepo) → 404 systématique en production, installation
 * client impossible. Ce test verrouille les endpoints publics de
 * EdgeDownloadController : 200 avec le contenu réel du repo, manifeste
 * d'intégrité sha256 cohérent, et 404 pour un asset inconnu.
 */
class EdgeDownloadControllerTest extends TestCase
{
    private const REPO_ROOT = __DIR__.'/../../../..';

    public function test_install_script_endpoint_returns_200_and_repo_content(): void
    {
        $response = $this->get('/api/v1/edge/install.sh');

        $response->assertOk();
        $this->assertAssetMatchesRepoFile($response, 'install.sh');
    }

    public function test_docker_compose_endpoint_returns_200_and_repo_content(): void
    {
        $response = $this->get('/api/v1/edge/download/docker-compose.yml');

        $response->assertOk();
        $this->assertAssetMatchesRepoFile($response, 'docker-compose.yml');
    }

    public function test_caddyfile_endpoint_returns_200_and_repo_content(): void
    {
        $response = $this->get('/api/v1/edge/download/Caddyfile.edge');

        $response->assertOk();
        $this->assertAssetMatchesRepoFile($response, 'Caddyfile.edge');
    }

    public function test_sha256_manifest_lists_all_assets_with_matching_hashes(): void
    {
        $response = $this->getJson('/api/v1/edge/download/sha256.txt');

        $response->assertOk()
            ->assertJsonPath('algorithm', 'sha256');

        $lines = $response->json('sha256');
        $this->assertIsArray($lines);
        $this->assertCount(3, $lines);

        $assets = ['install.sh', 'docker-compose.yml', 'Caddyfile.edge'];
        foreach ($lines as $line) {
            [$hash, $filename] = preg_split('/\s+/', (string) $line) ?: ['', ''];
            $this->assertContains($filename, $assets);
            $repoFile = self::REPO_ROOT.'/edge/'.$filename;
            $this->assertFileExists($repoFile);
            $this->assertSame(hash_file('sha256', $repoFile), $hash, "hash mismatch for {$filename}");
        }
    }

    public function test_unknown_asset_returns_404(): void
    {
        $this->get('/api/v1/edge/download/not-a-real-file.yml')->assertNotFound();
    }

    /**
     * #7653 — sans clé privée configurée, la signature du manifeste est
     * indisponible (503 explicite) : install.sh est fail-closed côté client.
     */
    public function test_sha256_signature_returns_503_without_signing_key(): void
    {
        config(['edge.license_private_key' => null]);

        $this->getJson('/api/v1/edge/download/sha256.txt.sig')
            ->assertStatus(503)
            ->assertJsonPath('error', 'edge_manifest_signing_key_not_configured');
    }

    /**
     * #7653 — la signature RS256 servie doit vérifier les octets EXACTS du
     * manifeste sha256.txt avec la clé publique (c'est le contrat install.sh :
     * `openssl dgst -sha256 -verify pub.pem -signature sig sha256.txt`), et
     * échouer sur un manifeste altéré.
     */
    public function test_sha256_signature_verifies_manifest_bytes_and_rejects_tampering(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);

        $privatePem = '';
        $this->assertTrue(openssl_pkey_export($key, $privatePem));
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $publicPem = $details['key'];
        $this->assertIsString($publicPem);

        config(['edge.license_private_key' => $privatePem]);

        $manifest = $this->get('/api/v1/edge/download/sha256.txt');
        $manifest->assertOk();
        $manifestBody = (string) $manifest->getContent();

        $signatureResponse = $this->get('/api/v1/edge/download/sha256.txt.sig');
        $signatureResponse->assertOk();

        $signature = base64_decode((string) $signatureResponse->getContent(), true);
        $this->assertNotFalse($signature, 'signature must be valid base64');

        $this->assertSame(
            1,
            openssl_verify($manifestBody, $signature, $publicPem, OPENSSL_ALGO_SHA256),
            'signature must verify the exact manifest bytes',
        );

        $this->assertNotSame(
            1,
            openssl_verify($manifestBody.'tampered', $signature, $publicPem, OPENSSL_ALGO_SHA256),
            'signature must NOT verify a tampered manifest',
        );
    }

    private function assertAssetMatchesRepoFile(TestResponse $response, string $filename): void
    {
        $repoFile = self::REPO_ROOT.'/edge/'.$filename;
        $this->assertFileExists($repoFile, "repo asset edge/{$filename} missing");
        $this->assertSame(
            (string) file_get_contents($repoFile),
            $response->getContent(),
            "served content differs from edge/{$filename}"
        );
    }
}
