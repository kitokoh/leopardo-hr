<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ExpenseClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseClaim
 */
class ExpenseClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'category' => $this->category,
            'description' => $this->description,
            'receipt_url' => $this->receipt_url,
            'expense_date' => $this->expense_date?->toDateString(),
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
