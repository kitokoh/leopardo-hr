<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #8760 — Message d'un client dans sa conversation publique.
 *
 * Longueur bornée côté serveur : un client ne peut pas injecter 10 Mo de texte
 * dans un fil partagé avec le professionnel.
 */
class PostPublicConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
