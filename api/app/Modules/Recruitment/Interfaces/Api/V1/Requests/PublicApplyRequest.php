<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use App\Rules\NotPrivateUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PublicApplyRequest — validation for anonymous candidate applications
 * submitted through the public careers portal.
 */
class PublicApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string|max:5000',
            // Issue #5588 : garde SSRF — resume_url doit être https public
            // (rejet des IP privées/réservées/loopback, hôtes locaux).
            // Inerte aujourd'hui (jamais fetché) mais un futur job de parsing
            // de CV ne devra pas pouvoir lire le réseau interne.
            'resume_url' => ['nullable', 'url', 'max:500', new NotPrivateUrl()],
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'source' => 'nullable|in:website,linkedin,referral,agency,other',
            // BC-10 (#9100) — RGPD : le consentement explicite est OBLIGATOIRE.
            // Sans lui, la candidature est rejetée en 422 : conserver les
            // données personnelles d'un candidat sans base légale documentée
            // serait une non-conformité, pas un simple défaut d'UX.
            'consent' => ['required', 'accepted'],
            // Anti-spam : champ piège invisible côté formulaire. Un bot le
            // remplit, un humain ne le voit pas. `prohibited` rejette toute
            // valeur non vide sans révéler le mécanisme.
            'website' => ['nullable', 'prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'Le consentement au traitement de vos données est requis pour candidater.',
            'website.prohibited' => 'Candidature refusée.',
        ];
    }
}
