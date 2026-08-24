<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Expense\Domain\Models\ExpenseAccountingEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseAccountingEntry
 */
class ExpenseAccountingEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_claim_id' => $this->expense_claim_id,
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
