<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Notification;

use Illuminate\Foundation\Http\FormRequest;

class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:80'],
            'unread' => ['nullable', 'boolean'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
