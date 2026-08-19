<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Même policy produit (#3305) que StorePayrollRequest : écriture
        // réservée principal comptable / RH.
        /** @var Employee|null $actor */
        $actor = $this->user();

        if ($actor === null || ! $actor->hasManagerRole('principal', 'rh')) {
            return false;
        }

        // Le manager ne peut modifier que les fiches de SA société.
        $payroll = $this->route('payroll');

        return $payroll instanceof Payroll ? $payroll->company_id === $actor->company_id : true;
    }

    public function rules(): array
    {
        return ['gross_salary' => ['sometimes', 'numeric', 'min:0'], 'overtime_amount' => ['sometimes', 'numeric', 'min:0'], 'bonuses' => ['sometimes', 'array'], 'bonuses.*.label' => ['required_with:bonuses', 'string', 'max:100'], 'bonuses.*.amount' => ['required_with:bonuses', 'numeric', 'min:0'], 'deductions' => ['sometimes', 'array'], 'deductions.*.label' => ['required_with:deductions', 'string', 'max:100'], 'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'], 'cotisations' => ['sometimes', 'array'], 'ir_amount' => ['sometimes', 'numeric', 'min:0'], 'advance_deduction' => ['sometimes', 'numeric', 'min:0'], 'absence_deduction' => ['sometimes', 'numeric', 'min:0'], 'penalty_deduction' => ['sometimes', 'numeric', 'min:0']];
    }
}
