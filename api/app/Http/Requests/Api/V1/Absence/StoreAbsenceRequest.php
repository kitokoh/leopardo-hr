<?php

namespace App\Http\Requests\Api\V1\Absence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'absence_type_id' => [
                'required',
                'integer',
                Rule::exists('absence_types', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (! is_string($startDate) || ! is_string($endDate)) {
                return;
            }

            if ($endDate < $startDate) {
                $validator->errors()->add('end_date', 'La date de fin doit être postérieure ou égale à la date de début.');
            }
        });
    }
}
