<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduFeePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encaissement en lot au guichet (#9791).
 *
 * Le lot porte le CONTEXTE COMMUN (moyen de paiement, référence de bordereau,
 * date d'encaissement, `external_id` de lot) et une liste de lignes
 * `{charge_id, amount}`. Une ligne peut surcharger sa référence (un bordereau
 * par famille dans un même dépôt).
 *
 * La validation structurelle vit ici ; les règles MÉTIER (charge du tenant,
 * non terminale, non surdébit, devise, doublon de charge) vivent dans
 * `RecordEduFeeBatchPaymentsAction`, qui les vérifie TOUTES avant d'écrire et
 * renvoie les lignes fautives indexées.
 */
class StoreEduFeeBatchPaymentRequest extends FormRequest
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
            'method' => ['required', Rule::in(EduFeePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
            'external_id' => ['nullable', 'string', 'max:100'],

            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.charge_id' => ['required', 'integer', 'min:1'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'lines.*.currency' => ['nullable', 'string', 'size:3'],
            'lines.*.reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'EDU_FEE_BATCH_EMPTY',
            'lines.min' => 'EDU_FEE_BATCH_EMPTY',
            'lines.max' => 'EDU_FEE_BATCH_TOO_LARGE',
        ];
    }
}
