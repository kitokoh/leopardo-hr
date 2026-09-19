<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Issue #7737 — Réservation via la marketplace publique inter-agences.
 *
 * Sous-ensemble du contrat guichet (`StoreTravelBookingRequest`) : la source
 * est forcée à `marketplace` par le contrôleur (jamais acceptée du client),
 * pas de compte corporate ni de devis sur cette surface. `idempotency_key`
 * obligatoire : un rejeu réseau ne crée jamais deux réservations.
 */
class StoreTravelMarketplaceBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // surface publique : l'éligibilité du trajet est tranchée par le contrôleur
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ages = array_column(AgeCategory::cases(), 'value');
        $documents = array_column(DocumentType::cases(), 'value');

        return [
            'trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'notify_consent' => ['nullable', 'boolean'],
            'passengers' => ['required', 'array', 'min:1', 'max:20'],
            'passengers.*.full_name' => ['required', 'string', 'max:160'],
            'passengers.*.birth_date' => ['nullable', 'date'],
            'passengers.*.document_type' => ['nullable', 'string', Rule::in($documents)],
            'passengers.*.document_number' => ['nullable', 'string', 'max:40'],
            'passengers.*.age_category' => ['required', 'string', Rule::in($ages)],
            'passengers.*.class_id' => ['required', 'integer', 'exists:travel_classes,id'],
            'passengers.*.seat_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
