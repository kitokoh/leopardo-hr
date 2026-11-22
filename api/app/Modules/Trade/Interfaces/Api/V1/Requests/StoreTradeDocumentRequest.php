<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Depot d'un document commercial — #9779.
 *
 * ## Ce que la requete n'accepte PAS
 *
 * - **`disk` et `path`** : le stockage est une decision SERVEUR (disque
 *   `private`, chemin genere, jamais devinable). Les accepter permettrait
 *   d'ecrire ou de faire pointer un document vers un fichier qu'on ne devrait
 *   pas lire.
 * - **`company_id`** : pose par `BelongsToCompany`. Idem — anti-spoof #7646.
 *
 * ## Rattachement
 *
 * Un document appartient a au moins UN dossier (commande, offre, RFQ ou
 * transaction). Un document orphelin serait introuvable dans l'ecran du
 * vendeur, donc inutile ; on l'exige.
 *
 * `size_bytes` est CONSTATE par le serveur depuis le fichier recu, jamais
 * declare : un client peut mentir sur la taille, `getSize()` non.
 */
final class StoreTradeDocumentRequest extends FormRequest
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
        return [
            'file' => ['required', 'file', 'max:20480'], // 20 Mo
            'type' => ['required', 'string', Rule::in(TradeDocumentType::values())],
            'title' => ['required', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::in(['draft'])],
            // Au moins un rattachement : cf. docblock de classe.
            'trade_order_id' => ['nullable', 'integer', 'min:1'],
            'trade_quote_id' => ['nullable', 'integer', 'min:1'],
            'trade_rfq_id' => ['nullable', 'integer', 'min:1'],
            'trade_deal_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => __('trade.documents.type_invalid'),
            'title.required' => __('trade.documents.title_required'),
        ];
    }

    /**
     * Le document doit etre rattache a un dossier : on verifie ici qu'au moins
     * un des liens est fourni, sinon le fichier serait depose sans aucun acces
     * depuis l'ecran.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $links = ['trade_order_id', 'trade_quote_id', 'trade_rfq_id', 'trade_deal_id'];

            foreach ($links as $link) {
                if ($this->filled($link)) {
                    return;
                }
            }

            $validator->errors()->add('file', __('trade.documents.link_required'));
        });
    }
}
