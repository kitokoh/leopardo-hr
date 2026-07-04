<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Public endpoints for Edge node installation assets.
 * No authentication required — consumed by the install script.
 */
class EdgeDownloadController extends Controller
{
    /** GET /edge/install.sh */
    public function installScript(): Response
    {
        $scriptPath = base_path('edge/install.sh');

        abort_unless(file_exists($scriptPath), 404, 'Install script not found.');

        return response((string) file_get_contents($scriptPath), 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename="install.sh"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /** GET /edge/download/docker-compose.yml */
    public function dockerCompose(): Response
    {
        $filePath = base_path('edge/docker-compose.yml');

        abort_unless(file_exists($filePath), 404, 'docker-compose.yml not found.');

        return response((string) file_get_contents($filePath), 200, [
            'Content-Type'        => 'text/yaml',
            'Content-Disposition' => 'attachment; filename="docker-compose.yml"',
            'Cache-Control'       => 'public, max-age=3600',
        ]);
    }

    /** GET /edge/license-public-key */
    public function licensePublicKey(): Response
    {
        $publicKey = config('edge.license_public_key', '');

        if (empty($publicKey)) {
            $keyPath = base_path('edge/keys/edge_license_public.pem');
            if (file_exists($keyPath)) {
                $publicKey = (string) file_get_contents($keyPath);
            }
        }

        abort_unless(!empty($publicKey), 503, 'License public key not configured.');

        return response($publicKey, 200, [
            'Content-Type'  => 'text/plain',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
