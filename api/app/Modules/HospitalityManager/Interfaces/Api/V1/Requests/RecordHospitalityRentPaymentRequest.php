<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Requests;

use App\Modules\HospitalityManager\Domain\Models\HospitalityRentPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encaissement d'une échéance de loyer — HOSP-005 (#7947, BC-32).
 *
 * `amount_minor` est le montant ENCAISSÉ MAINTENANT (pas le cumul) : il
 * s'ajoute à `paid_amount_minor`. Le dépassement du dû est refusé par le
 * service (422 RENT_AMOUNT_EXCEEDS_OUTSTANDING), pas tronqué.
 */
class RecordHospitalityRentPaymentRequest extends FormRequest
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
            'amount_minor' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'transfer', 'card', 'cheque', 'mobile_money'])],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Statuts acceptés par la machine à états — exposé pour les tests de
     * contrat (le catalogue doit rester aligné sur le modèle).
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return HospitalityRentPayment::STATUSES;
    }
}
