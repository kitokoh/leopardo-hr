<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBankExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => 'required|in:sepa_xml,ccp_dz,virement_ma,csv_generic',
        ];
    }
}
