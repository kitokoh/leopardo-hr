<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Application\Actions;

use App\Modules\EdgeSync\Application\Services\EdgeLicenseService;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Support\Str;

/**
 * Enregistre un nouveau noeud Edge et emet sa premiere licence.
 */
class RegisterEdgeNode
{
    public function __construct(
        private readonly EdgeLicenseService $licenseService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{node: EdgeNode, license: EdgeLicense, edge_token: string}
     */
    public function execute(int $companyId, array $data): array
    {
        $edgeToken = Str::random(64);

        $node = EdgeNode::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name'].'-'.Str::random(6)),
            'site_address' => $data['site_address'] ?? null,
            'status' => 'active',
            'mode' => $data['mode'] ?? 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => $data['capabilities'] ?? [],
            'license_expires_at' => now()->addDays(config('edge.license_validity_days', 30)),
            // The plaintext token is never persisted: only its SHA-256 digest is
            // stored, matching the hashed-secret pattern already used by the
            // ZKTeco kiosk (AttendanceKiosk.sync_token_hash). The plaintext value
            // is returned once, in the registration response only.
            'metadata' => ['edge_token' => hash('sha256', $edgeToken)],
        ]);

        $license = $this->licenseService->issueLicense(
            $node,
            config('edge.license_validity_days', 30)
        );

        return [
            'node' => $node,
            'license' => $license,
            'edge_token' => $edgeToken,
        ];
    }
}
