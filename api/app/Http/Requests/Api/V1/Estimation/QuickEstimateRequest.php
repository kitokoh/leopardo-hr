<?php

namespace App\Http\Requests\Api\V1\Estimation;

use Illuminate\Foundation\Http\FormRequest;

class QuickEstimateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                'before_or_equal:'.now()->addDay()->toDateString(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $from = $this->input('from');
                    if (! is_string($from) || ! is_string($value)) {
                        return;
                    }

                    try {
                        $fromDate = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
                        $toDate = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if ($fromDate->diffInDays($toDate) > 365) {
                        $fail('The selected period may not exceed 365 days.');
                    }
                },
            ],
        ];
    }
}
