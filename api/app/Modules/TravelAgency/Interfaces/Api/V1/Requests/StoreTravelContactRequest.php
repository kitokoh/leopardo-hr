<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * Validation stricte : nom obligatoire, email OU téléphone (au moins un),
 * message borné (10..1000 caractères), consentement RGPD obligatoire
 * (`accepted`). Un `idempotency_key` client (uuid) permet de rejouer une
 * soumission sans créer deux événements (retry réseau, double clic).
 */
class StoreTravelContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Toute personne authentifiée du tenant peut soumettre le formulaire
        // (middleware auth:sanctum + tenant sur le groupe de routes).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:190'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'consent' => ['required', 'accepted'],
            'idempotency_key' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
