<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marketing OS — transition de statut d'une publication (brief §E, §F).
 *
 * Le contrat est volontairement explicite sur le point le plus sensible du
 * module : passer à `published` EXIGE `published_url`. Il est donc
 * structurellement impossible de marquer un contenu « publié » sans fournir
 * la preuve de la publication réelle (l'URL), ni parce qu'une page de réseau
 * social a simplement été ouverte.
 */
class UpdateMarketingPublicationStatusRequest extends FormRequest
{
    /** Transitions autorisées depuis l'interface. */
    public const ACTIONS = [
        'in_preparation',
        'in_review',
        'approved',
        'scheduled',
        'confirm_published',
        'failed',
        'cancelled',
    ];

    public function authorize(): bool
    {
        return true; // Garde portée par la route (platform.permission).
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'scheduled_at' => ['required_if:action,scheduled', 'nullable', 'date'],
            // Preuve de publication : obligatoire pour confirmer la mise en ligne.
            'published_url' => ['required_if:action,confirm_published', 'nullable', 'url', 'max:512'],
            'published_at' => ['nullable', 'date'],
            'platform_account' => ['nullable', 'string', 'max:120'],
            // #8530 — identifiant du post cote plateforme/agregateur, requis
            // pour interroger l'API de statistiques. Renseigne uniquement
            // lorsqu'une publication a REELLEMENT ete executee par une API.
            'external_post_id' => ['nullable', 'string', 'max:191'],
            'error_message' => ['required_if:action,failed', 'nullable', 'string', 'max:2000'],
            'stats' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'published_url.required_if' => 'L’URL réelle de la publication est obligatoire pour confirmer une mise en ligne.',
            'error_message.required_if' => 'Le message d’erreur est obligatoire pour marquer une publication en échec.',
            'scheduled_at.required_if' => 'La date de publication est obligatoire pour planifier.',
        ];
    }
}
