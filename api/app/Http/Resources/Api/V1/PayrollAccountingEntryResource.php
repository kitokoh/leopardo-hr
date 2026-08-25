<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Payroll\Domain\Models\PayrollAccountingEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollAccountingEntry
 */
class PayrollAccountingEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'pay_slip_id' => $this->pay_slip_id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'account_code' => $this->account_code,
            'account_label' => $this->account_label,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'reference' => $this->reference,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
