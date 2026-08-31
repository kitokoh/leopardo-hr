<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-802 (#6093) — Création d'un aller-retour combiné.
 *
 * Les passagers sont partagés entre les deux sens ; chaque sens réserve ses
 * propres sièges (inventaire distinct par trajet).
 */
class StoreTravelRoundTripRequest extends FormRequest
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
        $sources = array_map(fn (BookingSource $source): string => $source->value, BookingSource::cases());
        $ages = array_map(fn (AgeCategory $age): string => $age->value, AgeCategory::cases());
        $documents = array_map(fn (DocumentType $type): string => $type->value, DocumentType::cases());

        return [
            'outbound_trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'return_trip_id' => ['required', 'integer', 'different:outbound_trip_id', 'exists:travel_trips,id'],
            'booking_source' => ['required', 'string', Rule::in($sources)],
            'idempotency_key' => ['required', 'string', 'max:255'],
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
