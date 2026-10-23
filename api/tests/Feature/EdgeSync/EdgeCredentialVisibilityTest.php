<?php

declare(strict_types=1);

namespace Tests\Feature\EdgeSync;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #4687 — les credentials Edge (license_key, metadata, signed_payload) ne
 * doivent jamais fuiter dans les sérialisations par défaut ; ils ne sont
 * exposés qu'aux moments légitimes d'émission (store / issueLicense) via
 * `makeVisible()` dans EdgeNodeController.
 *
 * Les routes tenant /api/v1/edge (register/list/show) ont été démontées
 * (#1291) — le contrat est validé au niveau MODÈLE (serialization), qui est
 * exactement la garantie de sécurité apportée par $hidden.
 */
class EdgeCredentialVisibilityTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private EdgeNode $node;

    private EdgeLicense $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'visibility-test-co',
            'status' => 'active',
        ]);
        $this->company = $company;

        /** @var EdgeNode $node */
        $node = EdgeNode::create([
            'company_id' => $this->company->id,
            'name' => 'Site Principal',
            'slug' => 'site-principal-visibility',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => [],
            'license_expires_at' => now()->addDays(30),
            'license_key' => 'lk-visibility-secret',
            'metadata' => ['edge_token' => hash('sha256', 'visibility-token')],
        ]);
        $this->node = $node;

        /** @var EdgeLicense $license */
        $license = EdgeLicense::forceCreate([
            'company_id' => $this->company->id,
            'edge_node_id' => $node->id,
            'license_key' => 'lk-visibility-secret',
            'signed_payload' => 'signed-payload-visibility',
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'validation_status' => 'valid',
        ]);
        $this->license = $license;
    }

    /** @test */
    public function edge_node_never_serializes_credentials_by_default(): void
    {
        $data = $this->node->toArray();

        $this->assertArrayNotHasKey('license_key', $data, 'license_key ne doit pas être sérialisé par défaut.');
        $this->assertArrayNotHasKey('metadata', $data, 'metadata (hash edge_token) ne doit pas être sérialisé par défaut.');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        // `$data` vient de `toArray()` : Larastan en deduit un shape dont
        // toutes les cles sont optionnelles, donc l'acces direct `$data['name']`
        // est signale `offsetAccess.notFound` au niveau 8. `assertIsString`
        // porte l'invariant de presence ET de type, ce qui est exactement ce
        // que ce test verifie.
        $this->assertIsString($data['name'] ?? null);
        $this->assertSame('Site Principal', $data['name']);
    }

    /** @test */
    public function edge_license_never_serializes_credentials_by_default(): void
    {
        $data = $this->license->toArray();

        $this->assertArrayNotHasKey('license_key', $data, 'license_key ne doit pas être sérialisé par défaut.');
        $this->assertArrayNotHasKey('signed_payload', $data, 'signed_payload ne doit pas être sérialisé par défaut.');
        $this->assertArrayHasKey('id', $data);
    }

    /** @test */
    public function credentials_are_exposed_explicitly_at_emission(): void
    {
        // L'émission (store / issueLicense) ré-expose explicitement les
        // credentials via makeVisible() — contrat « montré une seule fois ».
        $nodeData = $this->node->makeVisible(['license_key', 'metadata'])->toArray();
        // Meme raison que pour $licenseData ci-dessous : shape a cles
        // optionnelles issu de `toArray()`.
        $nodeLicenseKey = $nodeData['license_key'] ?? null;
        $this->assertSame('lk-visibility-secret', $nodeLicenseKey);

        $licenseData = $this->license->makeVisible(['license_key', 'signed_payload'])->toArray();
        // `toArray()` produit un shape a cles toutes optionnelles : l'acces
        // direct est signale `offsetAccess.notFound`. Capturer via `?? null`
        // conserve exactement l'assertion (une cle absente donne `null`, qui
        // fait echouer le `assertSame` attendu) tout en satisfaisant le typage.
        $licenseKey = $licenseData['license_key'] ?? null;
        $signedPayload = $licenseData['signed_payload'] ?? null;
        $this->assertSame('lk-visibility-secret', $licenseKey);
        $this->assertSame('signed-payload-visibility', $signedPayload);
    }
}
