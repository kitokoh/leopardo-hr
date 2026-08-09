<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Planning\Domain\Models\ExpenseClaim;
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
            // Audit passe 3 (#1608) : `amount` était un champ fantôme (absent du
            // modèle, toujours null) ; le champ réel est `total_amount`. On
            // expose la valeur réelle, avec `amount` conservé en alias pour la
            // compatibilité des clients existants.
            'total_amount' => $this->total_amount,
            'amount' => $this->total_amount,
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

