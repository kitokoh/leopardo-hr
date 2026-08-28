<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un contact CRM — Issue #5711 (CRM-V0-07).
 *
 * Même contrat que la création : primaire unique par compte (hors contact
 * courant), champs inconnus refusés.
 */
class UpdateCrmContactRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'max:191', 'email:rfc'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function ($validator): void {
            if (! $this->boolean('is_primary')) {
                return;
            }

            $exists = DB::table('crm_contacts')
                ->where('company_id', $this->user()?->company_id)
                ->where('account_id', (int) $this->route('contact')->account_id)
                ->where('is_primary', true)
                ->where('id', '!=', (int) $this->route('contact')->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('is_primary', 'Un contact primaire existe déjà pour ce compte.');
            }
        });
    }
}
