<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use App\Modules\Trade\Domain\Enums\TradeVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation d'une contrepartie (client, fournisseur, transporteur...) — #9779.
 *
 * ## Pourquoi ce FormRequest existe
 *
 * `trade_counterparties` avait un modele, une table et une factory, mais
 * AUCUNE route. Or `POST /trade/rfqs/{rfq}/send` exige `counterparty_ids`
 * (`min:1`) : un tenant reel ne pouvait donc jamais diffuser une demande de
 * prix, faute de pouvoir creer la contrepartie a qui l'envoyer. La seule
 * maniere d'en avoir une etait le factory de test.
 *
 * ## Deux champs volontairement NON acceptes
 *
 * - **`company_id`** : pose par `BelongsToCompany` depuis le tenant courant
 *   (anti-spoof #7646). L'accepter permettrait d'ecrire chez le voisin.
 * - **`verification_status` = `verified`** : la verification est une saisie
 *   MANUELLE TRACEE (`verified_by_employee_id`, `verified_at`). Un client ne
 *   peut donc que demander `pending` — declarer soi-meme `verified` ferait
 *   passer une simple declaration pour un controle qui n'a pas eu lieu.
 */
final class StoreCounterpartyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            // ISO 3166-1 alpha-2 : meme format que `hq_country` du profil.
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            // Au moins un role : une contrepartie sans role ne peut servir a
            // rien dans le cycle (on ne saurait pas si on lui achete ou vend).
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(TradeCounterpartyRole::values())],
            // `pending` seulement : cf. docblock de classe.
            'verification_status' => ['nullable', 'string', Rule::in([TradeVerificationStatus::Pending->value])],
            'capabilities' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'crm_account_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => __('trade.counterparties.roles_required'),
            'roles.min' => __('trade.counterparties.roles_required'),
            'verification_status.in' => __('trade.counterparties.verification_not_self_assertable'),
        ];
    }
}
