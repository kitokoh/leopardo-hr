<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN,CM,CF,TD,CG,GA,GQ,CI,ML,BF,BJ,TG,NE',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
