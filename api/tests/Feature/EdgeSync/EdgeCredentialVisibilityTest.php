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
        $license = EdgeLicense::create([
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
        $this->assertArrayHasKey('license_key', $nodeData);
        $this->assertArrayHasKey('metadata', $nodeData);
        $this->assertSame('lk-visibility-secret', $nodeData['license_key']);

        $licenseData = $this->license->makeVisible(['license_key', 'signed_payload'])->toArray();
        $this->assertArrayHasKey('license_key', $licenseData);
        $this->assertArrayHasKey('signed_payload', $licenseData);
        $this->assertSame('signed-payload-visibility', $licenseData['signed_payload']);
    }
}
