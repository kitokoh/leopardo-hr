<?php

namespace App\Services\Cameras;

use App\Models\Cameras\Camera;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Génération/validation de stream_token JWT HS256 pour l'accès aux flux caméra.
 *
 * Référence : docs/vision/Leopardo_RH_Camera_Complet+1.pdf, section 6.4 et 3.3.
 *
 * Format : header.payload.signature — chaque segment encodé en base64url.
 *   header  = { "alg": "HS256", "typ": "JWT" }
 *   payload = {
 *     iss: <issuer>,
 *     sub: <employee_id>,
 *     company_id: <uuid>,
 *     cam: <camera_id>,
 *     type: "stream_token",
 *     iat: <unix>,
 *     exp: <unix>,
 *     jti: <uuid>
 *   }
 *
 * Aucune dépendance externe requise : HMAC-SHA256 via hash_hmac().
 */
class CameraStreamTokenService
{
    public const TYPE_STREAM = 'stream_token';

    public function secret(): string
    {
        $configured = Config::get('cameras.stream_token.secret');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = Config::get('app.key');

        if (! is_string($appKey) || $appKey === '') {
            throw new RuntimeException('APP_KEY/CAMERAS_STREAM_TOKEN_SECRET is not configured.');
        }

        // app.key est généralement préfixé "base64:..." — on dérive un secret
        // stable en le hashant pour ne pas exposer APP_KEY tel quel.
        return hash('sha256', $appKey);
    }

    public function ttlMinutes(): int
    {
        $ttl = (int) Config::get('cameras.stream_token.ttl_minutes', 240);

        return max(5, min($ttl, 24 * 60));
    }

    public function issuer(): string
    {
        return (string) Config::get('cameras.stream_token.issuer', 'leopardo-rh');
    }

    /**
     * Génère un JWT signé pour la caméra donnée.
     * Retourne [token, expires_at] pour persistance côté client.
     */
    public function issue(Camera $camera, int|string $actorEmployeeId): array
    {
        $now = Carbon::now('UTC');
        $exp = $now->copy()->addMinutes($this->ttlMinutes());

        $payload = [
            'iss' => $this->issuer(),
            'sub' => (string) $actorEmployeeId,
            'company_id' => (string) $camera->company_id,
            'cam' => (int) $camera->id,
            'type' => self::TYPE_STREAM,
            'iat' => $now->getTimestamp(),
            'exp' => $exp->getTimestamp(),
            'jti' => (string) Str::uuid(),
        ];

        $token = $this->encode($payload);

        return [
            'token' => $token,
            'expires_at' => $exp,
            'payload' => $payload,
        ];
    }

    /**
     * Décode + valide un JWT. Retourne le payload en tableau.
     *
     * Retourne null si la signature ou le format est invalide (pas d'exception).
     */
    public function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$encHeader, $encPayload, $encSig] = $parts;

        $header = $this->base64UrlDecode($encHeader);
        $payload = $this->base64UrlDecode($encPayload);
        $signature = $this->base64UrlDecode($encSig, true);

        if ($header === null || $payload === null || $signature === null) {
            return null;
        }

        $headerData = json_decode($header, true);
        $payloadData = json_decode($payload, true);

        if (! is_array($headerData) || ! is_array($payloadData)) {
            return null;
        }

        if (($headerData['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = hash_hmac('sha256', $encHeader.'.'.$encPayload, $this->secret(), true);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return $payloadData;
    }

    /**
     * Valide un token pour une caméra donnée. Retourne la "raison" d'échec si
     * invalide, null si valide.
     */
    public function invalidReasonFor(string $jwt, int $cameraId): ?string
    {
        $payload = $this->decode($jwt);

        if ($payload === null) {
            return 'token_invalid';
        }

        if (($payload['type'] ?? null) !== self::TYPE_STREAM) {
            return 'token_invalid';
        }

        if ((int) ($payload['cam'] ?? 0) !== $cameraId) {
            return 'camera_mismatch';
        }

        $exp = (int) ($payload['exp'] ?? 0);

        if ($exp <= Carbon::now('UTC')->getTimestamp()) {
            return 'token_expired';
        }

        return null;
    }

    /**
     * Encodage JWT HS256.
     */
    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $encHeader = $this->base64UrlEncode((string) json_encode($header));
        $encPayload = $this->base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', $encHeader.'.'.$encPayload, $this->secret(), true);
        $encSig = $this->base64UrlEncode($signature);

        return $encHeader.'.'.$encPayload.'.'.$encSig;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded, bool $binary = false): ?string
    {
        $padded = strtr($encoded, '-_', '+/');
        $padLen = 4 - (strlen($padded) % 4);

        if ($padLen > 0 && $padLen < 4) {
            $padded .= str_repeat('=', $padLen);
        }

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }
}
