<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Social;

use Illuminate\Foundation\Http\FormRequest;

class SimulateCotisationSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gross_salary' => 'required|numeric|min:0',
            'country_code' => 'required|string|in:DZ,MA,FR,TN,TR,SN',
        ];
    }
}
