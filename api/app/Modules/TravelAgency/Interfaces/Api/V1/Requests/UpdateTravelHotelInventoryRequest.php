<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * M4 — Ouverture / fermeture d'un intervalle d'inventaire pour une chambre.
 *
 * `rooms_total = 0` ferme la chambre sur la période (maintenance). L'intervalle
 * est borné à 90 nuits : au-delà, c'est du remplissage de masse, pas de la
 * gestion d'inventaire.
 */
class UpdateTravelHotelInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelHotelPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'rooms_total' => ['required', 'integer', 'min:0', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! is_string($from) || ! is_string($to)) {
                return;
            }

            $nights = (int) \Illuminate\Support\Carbon::parse($from)->diffInDays(\Illuminate\Support\Carbon::parse($to)) + 1;

            if ($nights > 90) {
                $validator->errors()->add('to', 'L\'intervalle d\'inventaire ne peut pas dépasser 90 nuits.');
            }
        });
    }
}
