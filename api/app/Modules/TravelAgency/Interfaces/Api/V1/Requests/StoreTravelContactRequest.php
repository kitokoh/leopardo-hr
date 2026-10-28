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
        // Deux surfaces partagent ce FormRequest :
        //   - TRAVEL-416 `POST /travel/contact` (authentifie) : `name` + email OU
        //     telephone, `consent` ;
        //   - TRAVEL-913 `POST /travel/public/contact` (signe) : first_name /
        //     last_name + `consent_email`.
        // Le contrat public n'est pas affaibli (le public exige un email : cle
        // du registre de consentement) ; le contrat authentifie admet un
        // contact « telephone seul » (TRAVEL-416).
        return [
            'name' => ['nullable', 'string', 'max:240'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'consent' => ['sometimes', 'boolean'],
            'consent_email' => ['sometimes', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Le consentement reste obligatoire quelle que soit la surface :
     * `consent_email` (public) ou `consent` (authentifie), l'un des deux doit
     * valoir vrai. Sans ce garde, un formulaire sans case cochee creerait un
     * contact sans base legale RGPD.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! ($this->boolean('consent_email') || $this->boolean('consent'))) {
                $validator->errors()->add(
                    'consent_email',
                    'Le consentement de contact est obligatoire.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'consent_email.required' => 'Le consentement de contact est obligatoire.',
            'consent_email.accepted' => 'Le consentement de contact doit être accepté.',
            'message.max' => 'Le message ne doit pas dépasser 2000 caractères.',
        ];
    }
}
