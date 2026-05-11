<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Training;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasManagerRole('principal', 'rh') ?? false;
    }

    public function rules(): array
    {
        return [
            'training_course_id' => 'required|integer|exists:training_courses,id',
            'title' => 'required|string|max:200',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'location' => 'nullable|string|max:200',
            'instructor' => 'nullable|string|max:200',
            'max_participants' => 'nullable|integer|min:1',
        ];
    }
}
