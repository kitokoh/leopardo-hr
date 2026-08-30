<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduFeePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encaissement sur une charge de frais (EDU-016, #5832).
 * `external_id` → rejeu idempotent ; devise contrôlée côté service
 * (EDU_FEE_CURRENCY_MISMATCH) ; non-surdébit garanti (EDU_FEE_OVERPAYMENT).
 */
class StoreEduFeePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'method' => ['required', Rule::in(EduFeePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
