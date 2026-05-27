<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;

class AddCommentTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:5000']];
    }
}
