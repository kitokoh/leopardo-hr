<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-910 (#6113) — Envoi manuel d'une notification à un contact
 * (remplace la file legacy gv-back). Message borné.
 */
class NotifyTravelContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rôles gestion tranchés au controller (hasManagerRole)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'channels' => ['nullable', 'array', 'max:2'],
            'channels.*' => ['string', 'in:email,app'],
        ];
    }
}
