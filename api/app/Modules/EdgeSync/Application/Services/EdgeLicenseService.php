<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Application\Services;

use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Support\Str;

/**
 * Manages signed offline licenses for Edge nodes.
 *
 * Uses RS256 JWT signed with the platform's private key.
 * The Edge node embeds the public key and can verify locally
 * without any Cloud connection.
 *
 * Key generation:
 *   openssl genrsa -out edge_license_private.pem 2048
 *   openssl rsa -in edge_license_private.pem -pubout -out edge_license_public.pem
 */
class EdgeLicenseService
{
    public function issueLicense(EdgeNode $node, int $validDays = 30): EdgeLicense
    {
        $payload = [
            'iss'              => config('app.url'),
            'sub'              => $node->id,
            'company_id'       => $node->company_id,
            'edge_node_id'     => $node->id,
            'allowed_features' => $node->capabilities['features'] ?? [],
            'max_employees'    => $node->capabilities['max_employees'] ?? 50,
            'iat'              => now()->timestamp,
            'exp'              => now()->addDays($validDays)->timestamp,
            'jti'              => Str::uuid()->toString(),
        ];

        $signed = $this->sign($payload);

        return EdgeLicense::updateOrCreate(
            ['edge_node_id' => $node->id],
            [
                'company_id'        => $node->company_id,
                'license_key'       => Str::uuid()->toString(),
                'signed_payload'    => $signed,
                'allowed_features'  => $payload['allowed_features'],
                'max_employees'     => $payload['max_employees'],
                'issued_at'         => now(),
                'expires_at'        => now()->addDays($validDays),
                'last_validated_at' => now(),
                'validation_status' => 'valid',
            ]
        );
    }

    public function validateLicense(string $signedPayload): array
    {
        if (empty($signedPayload)) {
            return ['valid' => false, 'error' => 'Empty payload'];
        }

        try {
            $decoded = $this->decode($signedPayload);

            return [
                'valid'   => true,
                'payload' => $decoded,
                'expires' => \Carbon\Carbon::createFromTimestamp($decoded['exp'])->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    public function revokeLicense(EdgeNode $node): void
    {
        EdgeLicense::where('edge_node_id', $node->id)
            ->update(['validation_status' => 'revoked']);
    }

    public function renewIfNeeded(EdgeNode $node): ?EdgeLicense
    {
        $license = EdgeLicense::where('edge_node_id', $node->id)->first();

        if (! $license || $license->needsRenewal()) {
            return $this->issueLicense($node, config('edge.license_validity_days', 30));
        }

        return null;
    }

    /**
     * Sign a payload as RS256 JWT.
     * Falls back to HS256 with app key when no RSA key configured (dev/test).
     */
    protected function sign(array $payload): string
    {
        $privateKey = config('edge.license_private_key');

        if ($privateKey && class_exists(\Firebase\JWT\JWT::class)) {
            return \Firebase\JWT\JWT::encode($payload, $privateKey, 'RS256');
        }

        // Dev/test fallback — HS256 with app key
        $header  = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body    = base64url_encode(json_encode($payload));
        $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", config('app.key'), true));

        return "$header.$body.$sig";
    }

    /**
     * Decode and verify a JWT.
     */
    protected function decode(string $token): array
    {
        $publicKey = config('edge.license_public_key');

        if ($publicKey && class_exists(\Firebase\JWT\Key::class)) {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($publicKey, 'RS256'));

            return (array) $decoded;
        }

        // Dev/test fallback — decode without verification
        $parts   = explode('.', $token);
        if (count($parts) < 2) {
            throw new \RuntimeException('Invalid JWT structure');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Invalid JWT payload');
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('Token expired');
        }

        return $payload;
    }
}

if (! function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
