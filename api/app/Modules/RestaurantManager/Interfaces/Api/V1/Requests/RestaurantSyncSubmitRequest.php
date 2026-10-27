<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Restaurant OS — REST-OS-7 Validation d'un lot d'operations hors ligne (#8609).
 *
 * La validation est STRICTE sur la forme (identifiant d'operation obligatoire,
 * horodatage obligatoire) mais VOLONTAIREMENT PERMISSIVE sur le contenu du
 * `payload` : chaque type d'operation a sa propre forme, et c'est le service de
 * synchronisation qui refuse ce qu'il ne sait pas appliquer. Une operation
 * refusee est conservee, jamais perdue.
 */
final class RestaurantSyncSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'min:1', 'max:200'],
            'operations.*.client_operation_id' => ['required', 'string', 'max:100'],
            'operations.*.operation_type' => ['required', 'string', 'max:60'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.occurred_at' => ['required', 'date'],
            'operations.*.terminal_id' => ['nullable', 'string', 'max:100'],
            'operations.*.target_entity_type' => ['nullable', 'string', 'max:60'],
            'operations.*.target_entity_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
