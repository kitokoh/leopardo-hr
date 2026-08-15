<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #2232 — DELETE /platform/edge/nodes/{nodeId} (et son alias
 * /admin/edge-nodes/{nodeId}/revoke) révoquait un nœud Edge sans valider
 * le paramètre de route. `nodeId` doit être un UUID canonique → 422 sur
 * format invalide (action destructive, ne doit jamais partir d'un
 * identifiant malformé).
 */
class RevokeEdgeNodeRequest extends FormRequest
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
