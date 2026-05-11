<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:3',
            'category' => 'nullable|in:travel,meal,transport,accommodation,equipment,other',
            'description' => 'nullable|string|max:2000',
            'receipt_url' => 'nullable|url|max:500',
            'expense_date' => 'required|date|before_or_equal:today',
        ];
    }
}
