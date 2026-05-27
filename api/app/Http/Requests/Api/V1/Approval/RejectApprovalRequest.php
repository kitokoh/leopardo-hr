<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Approval;

use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => 'required|string|max:500',
        ];
    }
}
