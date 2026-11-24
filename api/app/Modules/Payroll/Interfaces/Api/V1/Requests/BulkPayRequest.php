<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #9874 — corps de `POST /payroll-runs/{id}/bulk-pay`.
 *
 * Extrait de la validation en ligne du contrôleur (garde
 * `check-form-request-guard.py`, décision du dépôt « FormRequest partout »).
 *
 * `pay_slip_ids` reste OPTIONNEL : l'omettre conserve le comportement
 * historique « payer tout le run », donc les intégrations existantes ne
 * changent pas (PA2-PAY-005).
 */
class BulkPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La permission est vérifiée par le middleware de route et le contrôleur
        // (payroll.manage) ; ce FormRequest ne porte que la forme du corps.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'pay_slip_ids' => ['nullable', 'array', 'min:1'],
            'pay_slip_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Sous-ensemble de bulletins à payer, ou null pour « tout le run ».
     *
     * @return array<int, int>|null
     */
    public function paySlipIds(): ?array
    {
        /** @var array<int, int>|null $ids */
        $ids = $this->validated('pay_slip_ids');

        return $ids;
    }
}
