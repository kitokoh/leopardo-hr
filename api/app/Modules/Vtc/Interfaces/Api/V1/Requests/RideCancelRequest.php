<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Annulation d'une course VTC par le passager (BC-34 VTC, VTC-03/#8359).
 *
 * Contrat `POST /v1/vtc/rides/{id}/cancel` — motif OBLIGATOIRE (tracé dans
 * la colonne dédiée, le journal append-only et l'événement de domaine,
 * spec §5.2).
 */
final class RideCancelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
