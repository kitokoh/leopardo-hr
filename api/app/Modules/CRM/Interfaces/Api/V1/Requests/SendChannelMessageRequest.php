<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envoi d'un message via un canal CRM (issue #5725).
 *
 * Contraintes : `to` normalisable (numéro/email), body OU template_name
 * obligatoire (jamais les deux vides), paramètres de template bornés.
 */
final class SendChannelMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:64'],
            'body' => ['sometimes', 'string', 'max:4000'],
            'template_name' => ['sometimes', 'string', 'max:100'],
            'template_parameters' => ['sometimes', 'array', 'max:10'],
            'template_parameters.*' => ['string', 'max:500'],
            'contact_id' => ['sometimes', 'string', 'max:64'],
            'purpose' => ['sometimes', 'string', 'in:transactional,marketing,service'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $body = (string) ($this->input('body') ?? '');
            $template = (string) ($this->input('template_name') ?? '');
            if ($body === '' && $template === '') {
                $validator->errors()->add('body', 'body ou template_name requis.');
            }
        });
    }
}
