<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Transfert d'une admission vers un autre lit — HC-006 (#7790).
 *
 * Ancien lit libéré + nouveau occupé atomiquement (service, transaction +
 * verrou) ; nouveau lit occupé → 409 `HEALTH_BED_OCCUPIED`.
 */
class TransferHealthAdmissionRequest extends FormRequest
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
            'bed_id' => ['required', 'integer'],
        ];
    }
}
