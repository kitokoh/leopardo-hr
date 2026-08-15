<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #2232 — POST /platform/edge/nodes/{nodeId}/sync (et son alias
 * /admin/edge-nodes/{nodeId}/sync) exécutait un sync forcé sans valider
 * le paramètre de route. `nodeId` doit être un UUID canonique (schéma
 * UUID edge_nodes, issue #1291) → 422 sur format invalide.
 */
class ForceSyncEdgeNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nodeId' => ['required', 'uuid'],
        ];
    }

    protected function validationData(): array
    {
        return [
            'nodeId' => $this->route('nodeId'),
        ];
    }
}
