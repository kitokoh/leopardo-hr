<?php

declare(strict_types=1);

namespace App\Modules\Planning\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class QuickEstimateRequest extends FormRequest
{
    /** @return array<string, mixed> */
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
                        $fromDate = (Carbon::createFromFormat('Y-m-d', $from) ?? now())->startOfDay();
                        $toDate = (Carbon::createFromFormat('Y-m-d', $value) ?? now())->startOfDay();
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
