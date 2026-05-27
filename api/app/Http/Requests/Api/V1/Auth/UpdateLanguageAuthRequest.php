<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLanguageAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => [
                'required',
                'string',
                'size:2',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! Language::isSupported($value)) {
                        $fail(__('validation.in', ['attribute' => $attribute];
    }
}
