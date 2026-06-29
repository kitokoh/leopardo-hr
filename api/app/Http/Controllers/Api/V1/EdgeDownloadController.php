<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Priority 5 — Public endpoints for Edge node installation.
 * No auth required — these are public download endpoints.
 */
class EdgeDownloadController extends Controller
{
    /**
     * Return the Edge install script.
     * GET /edge/install.sh
     */
    public function installScript(): Response
    {
        $scriptPath = base_path('edge/install.sh');

        abort_unless(file_exists($scriptPath), 404, 'Install script not found.');

        return response(file_get_contents($scriptPath), 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename="install.sh"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /**
     * Return the Edge docker-compose.yml.
     * GET /edge/download/docker-compose.yml
     */
    public function dockerCompose(): Response
    {
        $filePath = base_path('edge/docker-compose.yml');

        abort_unless(file_exists($filePath), 404, 'docker-compose.yml not found.');

        return response(file_get_contents($filePath), 200, [
            'Content-Type'        => 'text/yaml',
            'Content-Disposition' => 'attachment; filename="docker-compose.yml"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /**
     * Return the Edge license public key.
     * GET /edge/license-public-key
     * Called by Edge node at startup to verify its own license.
     */
    public function licensePublicKey(): Response
    {
        $publicKey = config('edge.license_public_key');

        abort_unless($publicKey, 503, 'License public key not configured.');

        return response($publicKey, 200, [
            'Content-Type'  => 'text/plain',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
