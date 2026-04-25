<?php

namespace App\Services\Cameras;

use App\Exceptions\DomainException;
use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraAccessLog;
use App\Models\Cameras\CameraAccessToken;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service métier du module Surveillance Caméras.
 * Centralise : contrôle de plan, génération de tokens tiers, création
 * de caméras, révocation, journalisation des accès.
 */
class CameraService
{
    public function __construct(private readonly CameraStreamTokenService $streamTokens) {}

    /**
     * Crée une caméra en s'assurant que la limite du plan n'est pas dépassée.
     */
    public function create(Company $company, Employee $actor, array $data): Camera
    {
        $this->assertPlanCanCreate($company);

        $camera = new Camera;
        $camera->company_id = $company->id;
        $camera->name = $data['name'];
        $camera->rtsp_url = $data['rtsp_url'];
        $camera->location = $data['location'] ?? null;
        $camera->sort_order = (int) ($data['sort_order'] ?? 0);
        $camera->stream_path_override = $data['stream_path_override'] ?? null;
        $camera->metadata = $data['metadata'] ?? [];
        $camera->created_by = $actor->id;
        $camera->is_active = true;
        $camera->save();

        $this->log(
            company: $company,
            camera: $camera,
            actor: $actor,
            action: 'create',
            reason: null,
            accessTokenId: null,
            ipAddress: null,
            metadata: ['name' => $camera->name]
        );

        return $camera->fresh();
    }

    public function update(Camera $camera, Employee $actor, array $data): Camera
    {
        foreach (['name', 'location', 'sort_order', 'stream_path_override', 'metadata', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $camera->{$field} = $data[$field];
            }
        }

        if (array_key_exists('rtsp_url', $data) && is_string($data['rtsp_url']) && $data['rtsp_url'] !== '') {
            $camera->rtsp_url = $data['rtsp_url'];
        }

        $camera->save();

        $this->log(
            company: $camera->company,
            camera: $camera,
            actor: $actor,
            action: 'update',
            reason: null,
            accessTokenId: null,
            ipAddress: null,
            metadata: ['fields' => array_keys($data)]
        );

        return $camera->fresh();
    }

    public function softDelete(Camera $camera, Employee $actor): void
    {
        $camera->is_active = false;
        $camera->save();
        $camera->delete();

        $this->log(
            company: $camera->company,
            camera: $camera,
            actor: $actor,
            action: 'delete',
            reason: null,
            accessTokenId: null,
            ipAddress: null,
            metadata: null
        );
    }

    /**
     * Produit la charge API pour l'app : caméra + stream_token signé.
     */
    public function buildStreamPayload(Camera $camera, Employee $actor): array
    {
        $issued = $this->streamTokens->issue($camera, $actor->id);

        return [
            'id' => (int) $camera->id,
            'name' => $camera->name,
            'location' => $camera->location,
            'is_active' => (bool) $camera->is_active,
            'sort_order' => (int) $camera->sort_order,
            'thumbnail_url' => $this->thumbnailUrl($camera),
            'stream_url' => $this->streamUrl($camera),
            'stream_token' => $issued['token'],
            'token_expires_at' => $issued['expires_at']->toIso8601ZuluString(),
            'created_at' => optional($camera->created_at)->toIso8601ZuluString(),
        ];
    }

    /**
     * Crée un token d'accès tiers (lien public partageable).
     */
    public function issueAccessToken(Camera $camera, Employee $actor, array $data): CameraAccessToken
    {
        $duration = (int) ($data['expires_in_minutes'] ?? 60);
        $allowed = Config::get('cameras.access_token_durations', []);
        $maxDuration = (int) Config::get('cameras.access_token_max_duration_minutes', 30 * 24 * 60);

        if (! empty($allowed) && ! in_array($duration, $allowed, true)) {
            throw new DomainException(
                'The requested duration is not allowed.',
                422,
                'VALIDATION_ERROR'
            );
        }

        $duration = max(1, min($duration, $maxDuration));

        $token = new CameraAccessToken;
        $token->company_id = $camera->company_id;
        $token->camera_id = $camera->id;
        $token->token = $this->generateOpaqueToken();
        $token->label = $data['label'] ?? null;
        $token->granted_to_email = $data['granted_to_email'] ?? null;
        $token->granted_to_name = $data['granted_to_name'] ?? null;
        $token->granted_by = $actor->id;
        $token->permissions = $data['permissions'] ?? ['view' => true];
        $token->ip_whitelist = $data['ip_whitelist'] ?? null;
        $token->expires_at = Carbon::now('UTC')->addMinutes($duration);
        $token->is_revoked = false;
        $token->save();

        $this->log(
            company: $camera->company,
            camera: $camera,
            actor: $actor,
            action: 'share',
            reason: null,
            accessTokenId: $token->id,
            ipAddress: null,
            metadata: ['label' => $token->label, 'expires_at' => $token->expires_at->toIso8601ZuluString()]
        );

        return $token->fresh();
    }

    public function revokeAccessToken(CameraAccessToken $token, Employee $actor): CameraAccessToken
    {
        if (! $token->is_revoked) {
            $token->is_revoked = true;
            $token->save();

            $this->log(
                company: $token->camera->company,
                camera: $token->camera,
                actor: $actor,
                action: 'revoke',
                reason: null,
                accessTokenId: $token->id,
                ipAddress: null,
                metadata: null
            );
        }

        return $token->fresh();
    }

    /**
     * Construit la réponse /internal/camera-token/verify pour MediaMTX.
     * Résout aussi bien un stream_token JWT qu'un access_token opaque.
     */
    public function verifyTokenForMediamtx(string $token, int $cameraId, ?string $clientIp): array
    {
        /** @var Camera|null $camera */
        $camera = Camera::withoutGlobalScopes()
            ->whereKey($cameraId)
            ->whereNull('deleted_at')
            ->first();

        if ($camera === null || ! $camera->is_active) {
            return ['allowed' => false, 'reason' => 'camera_not_found'];
        }

        /** @var Company|null $company */
        $company = Company::query()->find($camera->company_id);

        if ($company === null || in_array($company->status, ['suspended', 'expired'], true)) {
            $this->log(
                company: $company,
                camera: $camera,
                actor: null,
                action: 'token_verify_denied',
                reason: 'company_suspended',
                accessTokenId: null,
                ipAddress: $clientIp,
                metadata: null
            );

            return ['allowed' => false, 'reason' => 'company_suspended'];
        }

        if (! $company->hasFeature('cameras')) {
            $this->log(
                company: $company,
                camera: $camera,
                actor: null,
                action: 'token_verify_denied',
                reason: 'feature_disabled',
                accessTokenId: null,
                ipAddress: $clientIp,
                metadata: null
            );

            return ['allowed' => false, 'reason' => 'feature_disabled'];
        }

        // 1) Tentative stream_token JWT
        $reason = $this->streamTokens->invalidReasonFor($token, (int) $camera->id);
        if ($reason === null) {
            $this->log(
                company: $company,
                camera: $camera,
                actor: null,
                action: 'token_verify',
                reason: null,
                accessTokenId: null,
                ipAddress: $clientIp,
                metadata: ['type' => CameraStreamTokenService::TYPE_STREAM]
            );

            return [
                'allowed' => true,
                'company_id' => (string) $company->id,
                'type' => CameraStreamTokenService::TYPE_STREAM,
            ];
        }

        // 2) Tentative access_token tiers (opaque, table camera_access_tokens)
        /** @var CameraAccessToken|null $access */
        $access = CameraAccessToken::withoutGlobalScopes()
            ->where('token', $token)
            ->where('camera_id', $camera->id)
            ->first();

        if ($access === null) {
            $this->log(
                company: $company,
                camera: $camera,
                actor: null,
                action: 'token_verify_denied',
                reason: 'token_invalid',
                accessTokenId: null,
                ipAddress: $clientIp,
                metadata: null
            );

            return ['allowed' => false, 'reason' => 'token_invalid'];
        }

        $expiration = $access->expirationReason();

        if ($expiration !== null) {
            $this->log(
                company: $company,
                camera: $camera,
                actor: null,
                action: 'token_verify_denied',
                reason: $expiration,
                accessTokenId: $access->id,
                ipAddress: $clientIp,
                metadata: null
            );

            return ['allowed' => false, 'reason' => $expiration];
        }

        // Optionnel : whitelist IP (Phase 2, spec)
        if (is_array($access->ip_whitelist) && $access->ip_whitelist !== [] && $clientIp !== null) {
            if (! in_array($clientIp, $access->ip_whitelist, true)) {
                $this->log(
                    company: $company,
                    camera: $camera,
                    actor: null,
                    action: 'token_verify_denied',
                    reason: 'ip_not_allowed',
                    accessTokenId: $access->id,
                    ipAddress: $clientIp,
                    metadata: null
                );

                return ['allowed' => false, 'reason' => 'ip_not_allowed'];
            }
        }

        DB::table($access->getTable())
            ->where('id', $access->id)
            ->update([
                'last_used_at' => Carbon::now('UTC'),
                'use_count' => DB::raw('use_count + 1'),
            ]);

        $this->log(
            company: $company,
            camera: $camera,
            actor: null,
            action: 'token_verify',
            reason: null,
            accessTokenId: $access->id,
            ipAddress: $clientIp,
            metadata: ['type' => 'access_token']
        );

        return [
            'allowed' => true,
            'company_id' => (string) $company->id,
            'type' => 'access_token',
            'camera_id' => (int) $camera->id,
        ];
    }

    /**
     * Teste la joignabilité d'une URL RTSP via ffprobe (best-effort).
     * Retourne ['ok' => bool, 'error' => ?string].
     */
    public function testRtsp(string $rtspUrl): array
    {
        if (! (bool) Config::get('cameras.test_rtsp.enabled', true)) {
            return ['ok' => true, 'error' => null, 'skipped' => true];
        }

        $binary = (string) Config::get('cameras.test_rtsp.binary', 'ffprobe');
        $timeout = (int) Config::get('cameras.test_rtsp.timeout', 5);

        // Validation stricte pour éviter l'injection — seul rtsp:// est permis.
        if (! preg_match('#^rtsp://[^\s\'"]+$#i', $rtspUrl)) {
            return ['ok' => false, 'error' => 'invalid_url', 'skipped' => false];
        }

        $cmd = sprintf(
            '%s -v error -rtsp_transport tcp -stimeout %d -i %s -show_streams -of json 2>&1',
            escapeshellcmd($binary),
            $timeout * 1_000_000,
            escapeshellarg($rtspUrl)
        );

        $startedAt = microtime(true);
        $output = @shell_exec($cmd);
        $duration = microtime(true) - $startedAt;

        if ($output === null) {
            return ['ok' => false, 'error' => 'ffprobe_unavailable', 'skipped' => false];
        }

        $trimmed = trim((string) $output);

        if ($trimmed === '' || str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed ?: '{}', true);
            if (is_array($decoded) && isset($decoded['streams'])) {
                return ['ok' => true, 'error' => null, 'duration_ms' => (int) round($duration * 1000)];
            }
        }

        if ($duration >= $timeout) {
            return ['ok' => false, 'error' => 'timeout', 'skipped' => false];
        }

        return ['ok' => false, 'error' => 'connection_failed', 'skipped' => false];
    }

    public function log(
        ?Company $company,
        ?Camera $camera,
        ?Employee $actor,
        string $action,
        ?string $reason,
        ?int $accessTokenId,
        ?string $ipAddress,
        ?array $metadata,
    ): void {
        $log = new CameraAccessLog;
        $log->company_id = $company?->id ?? $camera?->company_id;
        $log->camera_id = $camera?->id;
        $log->employee_id = $actor?->id;
        $log->access_token_id = $accessTokenId;
        $log->actor_type = match (true) {
            $actor !== null => CameraAccessLog::ACTOR_EMPLOYEE,
            $accessTokenId !== null => CameraAccessLog::ACTOR_EXTERNAL_TOKEN,
            default => CameraAccessLog::ACTOR_SYSTEM,
        };
        $log->action = $action;
        $log->reason = $reason;
        $log->ip_address = $ipAddress;
        $log->metadata = $metadata;
        $log->save();
    }

    /**
     * Nombre de caméras (hors soft-deletées) d'une company.
     */
    public function countActive(Company $company): int
    {
        return Camera::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Limite max_cameras pour la company (null = illimité).
     * Source : companies.features.max_cameras, fallback plans.features.max_cameras,
     * fallback config cameras.default_max_cameras.
     */
    public function maxCameras(Company $company): ?int
    {
        $features = $company->features ?? [];

        if (array_key_exists('max_cameras', $features)) {
            $value = $features['max_cameras'];

            return $value === null ? null : (int) $value;
        }

        if ($company->plan_id) {
            $plan = DB::table('plans')->where('id', $company->plan_id)->first();
            $planFeatures = $plan && isset($plan->features) ? json_decode((string) $plan->features, true) : null;

            if (is_array($planFeatures) && array_key_exists('max_cameras', $planFeatures)) {
                return $planFeatures['max_cameras'] === null ? null : (int) $planFeatures['max_cameras'];
            }
        }

        return (int) Config::get('cameras.default_max_cameras', 0);
    }

    private function assertPlanCanCreate(Company $company): void
    {
        if (! $company->hasFeature('cameras')) {
            throw new DomainException(
                'Your plan does not include the cameras module. Upgrade to Business.',
                403,
                'FEATURE_NOT_ENABLED'
            );
        }

        $max = $this->maxCameras($company);

        if ($max === null) {
            return;
        }

        if ($this->countActive($company) >= $max) {
            throw new DomainException(
                'Camera limit reached for your plan ('.$max.' max). Upgrade to Enterprise.',
                403,
                'CAMERA_LIMIT_REACHED'
            );
        }
    }

    private function generateOpaqueToken(): string
    {
        // 32 bytes aléatoires → 64 caractères hex. Non devinable.
        return bin2hex(random_bytes(32));
    }

    private function thumbnailUrl(Camera $camera): ?string
    {
        if ($camera->thumbnail_path === null || $camera->thumbnail_path === '') {
            return null;
        }

        $base = Config::get('app.url');
        if (! is_string($base) || $base === '') {
            return $camera->thumbnail_path;
        }

        return rtrim($base, '/').'/storage/'.ltrim($camera->thumbnail_path, '/');
    }

    private function streamUrl(Camera $camera): string
    {
        $base = rtrim((string) Config::get('cameras.stream_base_url', 'wss://proxy.leopardo-rh.com/cam'), '/');
        $path = $camera->stream_path_override ?: (string) $camera->id;

        return $base.'/'.trim($path, '/').'/webrtc';
    }

    /**
     * Clé idempotente utilisable par les clients pour retry (Phase 2).
     */
    public function idempotencyKey(Camera $camera, Employee $actor): string
    {
        return Str::uuid()->toString().':'.$camera->id.':'.$actor->id;
    }
}
