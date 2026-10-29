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
        // Contrat canonique TRAVEL-416/913 (celui du front public, de
        // `SubmitTravelContactAction` et des tests) — restauré après la
        // variante `name`/`consent` introduite par la fusion de dette
        // 6ce743cee, qui rejetait en 422 le formulaire public réel (#8128).
        //
        // #8396 — `email` était `required` alors que le contrat annoncé, le
        // schéma (`email` nullable, unicité (company_id, phone)) et le front
        // public acceptent une soumission **email OU téléphone**. Un visiteur
        // sans email recevait un 422 : la seule entrée possible du tunnel de
        // lead était fermée aux prospects téléphone-seulement, courants en
        // Afrique de l'Ouest/Centrale. Au moins un des deux reste exigé.
        return [
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'consent_email' => ['required', 'accepted'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_email.required' => 'Le consentement de contact est obligatoire.',
            'consent_email.accepted' => 'Le consentement de contact doit être accepté.',
            'email.required_without' => 'Un email ou un téléphone est obligatoire.',
            'phone.required_without' => 'Un email ou un téléphone est obligatoire.',
            'message.min' => 'Le message doit contenir au moins 10 caractères.',
            'message.max' => 'Le message ne doit pas dépasser 1000 caractères.',
        ];
    }
}
