<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-312 (#6042) — Création d'une réservation guichet.
 *
 * Passagers imbriqués (1..20), chacun avec classe, catégorie d'âge, nom et
 * pièce d'identité optionnelle. `idempotency_key` obligatoire : un rejeu
 * réseau ne crée jamais deux réservations.
 */
class StoreTravelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelBookingPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sources = array_column(BookingSource::cases(), 'value');
        $ages = array_column(AgeCategory::cases(), 'value');
        $documents = array_column(DocumentType::cases(), 'value');

        return [
            'trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'booking_source' => ['required', 'string', Rule::in($sources)],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'customer_contact_id' => ['nullable', 'integer'],
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
