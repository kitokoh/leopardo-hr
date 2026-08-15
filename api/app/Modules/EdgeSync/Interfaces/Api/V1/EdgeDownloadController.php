<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Public endpoints for Edge node installation assets.
 * No authentication required — consumed by the install script.
 *
 * Issue #3591 / #3770 : les fichiers vivent à la racine du monorepo
 * (`edge/install.sh`, `edge/docker-compose.yml`, `edge/Caddyfile.edge`),
 * c'est-à-dire un niveau au-dessus du base_path Laravel (`api/`). En
 * production l'image copie `edge/` vers `/edge` (Dockerfile.prod) — les
 * deux emplacements sont résolus par {@see self::resolveEdgeAsset()}.
 */
class EdgeDownloadController extends Controller
{
    private const ASSETS = [
        'install.sh',
        'docker-compose.yml',
        'Caddyfile.edge',
    ];

    /** GET /edge/install.sh */
    public function installScript(): Response
    {
        $path = $this->resolveEdgeAsset('install.sh');

        return $this->downloadResponse($path, 'install.sh', 'text/plain');
    }

    /** GET /edge/download/docker-compose.yml */
    public function dockerCompose(): Response
    {
        $path = $this->resolveEdgeAsset('docker-compose.yml');

        return $this->downloadResponse($path, 'docker-compose.yml', 'text/yaml');
    }

    /** GET /edge/download/Caddyfile.edge */
    public function caddyfile(): Response
    {
        $path = $this->resolveEdgeAsset('Caddyfile.edge');

        return $this->downloadResponse($path, 'Caddyfile.edge', 'text/plain');
    }

    /**
     * GET /edge/download/sha256.txt
     *
     * Manifeste d'intégrité (issue #3770 / #3529) : le script d'installation
     * vérifie chaque fichier téléchargé contre ces empreintes avant de
     * l'écrire — fail-closed, aucune écriture si un hash ne correspond pas.
     */
    public function sha256(): JsonResponse
    {
        $lines = [];
        $missing = [];

        foreach (self::ASSETS as $asset) {
            $path = $this->resolveEdgeAsset($asset);
            if ($path === null || ! is_file($path)) {
                $missing[] = $asset;
                continue;
            }

            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                $missing[] = $asset;
                continue;
            }

            $lines[] = $hash.'  '.$asset;
        }

        if ($missing !== []) {
            return response()->json([
                'error' => 'edge_assets_missing',
                'missing' => $missing,
            ], 503);
        }

        return response()->json([
            'sha256' => $lines,
            'algorithm' => 'sha256',
        ]);
    }

    /**
     * GET /edge/download/docker-compose.yml.sha256
     *
     * Empreinte SHA-256 du docker-compose (convention `<fichier>.sha256`
     * utilisée par install.sh #3529) — plain text, premier token.
     */
    public function dockerComposeSha256(): Response
    {
        return $this->assetSha256('docker-compose.yml');
    }

    /**
     * GET /edge/download/Caddyfile.edge.sha256
     *
     * Empreinte SHA-256 du Caddyfile (convention `<fichier>.sha256`,
     * install.sh #3529) — plain text, premier token.
     */
    public function caddyfileSha256(): Response
    {
        return $this->assetSha256('Caddyfile.edge');
    }

    private function assetSha256(string $asset): Response
    {
        $path = $this->resolveEdgeAsset($asset);
        if ($path === null || ! is_file($path)) {
            abort(404, ucfirst($asset).' not found.');
        }

        $hash = hash_file('sha256', $path);

        return response($hash === false ? '' : $hash, 200, [
            'Content-Type'        => 'text/plain',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /** GET /edge/license-public-key */
    public function licensePublicKey(): Response
    {
        $publicKey = config('edge.license_public_key', '');

        if ($publicKey === '' || $publicKey === null) {
            $keyPath = base_path('edge/keys/edge_license_public.pem');
            if (is_file($keyPath)) {
                $publicKey = (string) file_get_contents($keyPath);
            }
        }

        if ($publicKey === '' || $publicKey === null) {
            return response(
                json_encode(['error' => 'edge_public_key_not_configured'], JSON_THROW_ON_ERROR),
                503,
                ['Content-Type' => 'application/json'],
            );
        }

        return response($publicKey, 200, [
            'Content-Type'  => 'text/plain',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Résout un asset Edge en tenant compte des deux emplacements possibles.
     *
     * @return string|null chemin absolu du fichier, ou null si introuvable
     */
    private function resolveEdgeAsset(string $file): ?string
    {
        $candidates = [
            base_path('../edge/'.$file), // repo : <racine>/edge/<file> (base_path = api/)
            base_path('edge/'.$file),    // image : /app/edge/<file> si copié dans api/
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function downloadResponse(?string $path, string $filename, string $contentType): Response
    {
        if ($path === null || ! is_file($path)) {
            abort(404, ucfirst($filename).' not found.');
        }

        $content = file_get_contents($path);

        return response($content === false ? '' : $content, 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }
}
