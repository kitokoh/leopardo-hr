<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use App\Modules\Marketing\Domain\Enums\MarketingPublicationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marketing OS — préparation d'une publication sur UNE plateforme (brief §E).
 *
 * `mode` n'est qu'une DEMANDE du client : le contrôleur le confronte à la
 * disponibilité réelle d'un connecteur (`social_accounts` actif) et retombe
 * sur `assisted` si l'automatisation n'est pas réellement possible. Un client
 * ne peut donc pas faire afficher « publication automatique » à tort.
 */
class StoreMarketingPublicationRequest extends FormRequest
{
    /**
     * Statuts qu'un client peut déclarer à la CRÉATION.
     *
     * `published` et `failed` en sont volontairement ABSENTS : ce sont des
     * états OBSERVÉS, jamais déclarés. Les accepter ici permettait de créer
     * une publication déjà « publiée » sans URL réelle — donc de contourner
     * la garantie centrale du module (« aucune publication sans preuve ») par
     * un simple POST. Ces deux états ne s'atteignent que par
     * `PATCH …/publications/{id}/status`, qui exige `published_url` /
     * `error_message`.
     *
     * @var list<string>
     */
    public const CREATABLE_STATUSES = [
        'idea',
        'in_preparation',
        'in_review',
        'approved',
        'scheduled',
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
            'content_idea_id' => ['nullable', 'integer', 'min:1'],
            'marketing_asset_id' => ['nullable', 'integer', 'min:1'],
            'platform' => ['required', 'string', 'max:40'],
            'mode' => ['nullable', 'string', Rule::in(MarketingPublicationMode::values())],
            'status' => ['nullable', 'string', Rule::in(self::CREATABLE_STATUSES)],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            'call_to_action' => ['nullable', 'string', 'max:500'],
            'destination_url' => ['nullable', 'url', 'max:512'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.in' => 'Le statut « published » ou « failed » ne peut pas être déclaré à la création : il s’obtient uniquement par une transition confirmée (URL réelle ou motif d’échec).',
        ];
    }
}
