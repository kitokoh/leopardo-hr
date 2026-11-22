<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Acte de vérification d'une contrepartie — BC-36 TRADE, #9780.
 *
 * ## Pourquoi un endpoint dédié
 *
 * `verification_status` n'est pas un champ comme les autres : il porte une
 * affirmation (« cette entreprise a été contrôlée »). Le séparer de la mise à
 * jour ordinaire garantit que :
 *
 *  - l'acte est **tracé** (`verified_by_employee_id`, `verified_at`) ;
 *  - il est réservé au rôle `admin` (route), pas au premier venu ;
 *  - il ne peut pas être posé par mégarde dans un `PUT` de masse.
 *
 * ## Ce que `verified` veut dire — et ne veut pas dire
 *
 * v1 : vérification **manuelle** par un membre de l'équipe. Aucun service de
 * vérification d'entreprise n'étant souscrit, `verified` ne doit jamais être
 * présenté comme le résultat d'un contrôle automatisé. La note est donc
 * **obligatoire** pour `verified` et `rejected` : une affirmation sans motif
 * n'est pas une vérification, c'est une case cochée.
 */
final class VerifyTradeCounterpartyRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in([
                    TradeVerificationStatus::Pending->value,
                    TradeVerificationStatus::Verified->value,
                    TradeVerificationStatus::Rejected->value,
                ]),
            ],
            // Une décision (verified/rejected) exige un motif ; `pending` n'en a pas besoin.
            'note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(static fn (): bool => in_array(
                    request()->input('status'),
                    [TradeVerificationStatus::Verified->value, TradeVerificationStatus::Rejected->value],
                    true,
                )),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Statut de vérification inconnu.',
            'note.required' => 'Une décision de vérification doit être motivée.',
        ];
    }
}
