<?php

namespace App\Http\Requests\Api\V1\Estimation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuickEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! is_string($from) || ! is_string($to)) {
                return;
            }

            try {
                $startDate = new \DateTime($from);
                $endDate = new \DateTime($to);

                if ($endDate < $startDate) {
                    $validator->errors()->add('to', 'The to date must be after the from date.');
                    return;
                }

                if ($startDate->diff($endDate)->days > 365) {
                    $validator->errors()->add('to', 'The period cannot exceed 365 days.');
                }
            } catch (\Exception $e) {
                // date_format:Y-m-d handles invalid date formats
            }
        });
    }
}
