<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Interfaces\Api\V1\Requests;

use App\Modules\Cameras\Domain\Models\CameraAlert;
use App\Modules\Cameras\Domain\Models\CameraEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload d'ingestion d'un événement détecté par la chaîne vidéo (#7427).
 *
 * `authorize()` renvoie `true` : la route est protégée par le secret partagé
 * MediaMTX (`EnsureMediamtxSecretMiddleware`), il n'y a pas d'utilisateur
 * Sanctum sur cette surface interne. La sévérité est optionnelle : en son
 * absence, `CameraAlertService::severityFor($type)` applique le défaut
 * documenté du type de détection.
 */
class StoreCameraEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'camera_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in(CameraEvent::TYPES)],
            'severity' => ['nullable', 'string', Rule::in(CameraAlert::SEVERITIES)],
            'detected_at' => ['nullable', 'date'],
            'snapshot_path' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
