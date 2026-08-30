<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-803 (#6094) — Création d'un devis de groupe.
 *
 * Le groupe doit comporter au moins MIN_GROUP_SIZE passagers ; le total est
 * toujours calculé côté serveur.
 */
class StoreTravelQuoteRequest extends FormRequest
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
        $ages = array_map(fn (AgeCategory $age): string => $age->value, AgeCategory::cases());
        $documents = array_map(fn (DocumentType $type): string => $type->value, DocumentType::cases());

        return [
            'trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'customer_contact_id' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'passengers' => ['required', 'array', 'min:5', 'max:50'],
            'passengers.*.full_name' => ['required', 'string', 'max:160'],
            'passengers.*.birth_date' => ['nullable', 'date'],
            'passengers.*.document_type' => ['nullable', 'string', Rule::in($documents)],
            'passengers.*.document_number' => ['nullable', 'string', 'max:40'],
            'passengers.*.age_category' => ['required', 'string', Rule::in($ages)],
            'passengers.*.class_id' => ['required', 'integer', 'exists:travel_classes,id'],
        ];
    }
}
