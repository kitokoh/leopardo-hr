<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Export;

use Illuminate\Foundation\Http\FormRequest;

class HistoryExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => 'nullable|in:json,csv,xlsx',
        ];
    }
}
