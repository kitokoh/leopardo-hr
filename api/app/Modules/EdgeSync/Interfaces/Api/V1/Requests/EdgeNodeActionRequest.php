<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation des actions Edge sur nodeId — QA wave 2026-08-14 T007 (#2232).
 *
 * Les endpoints sync / forceSync / revokeNode acceptaient n'importe quelle
 * valeur de `nodeId` (aucune validation). Les noeuds Edge portent un identifiant
 * UUID (EdgeNode::$keyType = string, $incrementing = false) : on exige le
 * format uuid pour produire un 422 explicite avant tout accès DB.
 */
class EdgeNodeActionRequest extends FormRequest
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
            'nodeId' => ['required', 'string', 'uuid'],
        ];
    }
}
