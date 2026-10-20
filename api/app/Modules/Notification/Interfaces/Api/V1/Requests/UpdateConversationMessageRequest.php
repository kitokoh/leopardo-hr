<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #8747 [BOS-CHAT] — édition du contenu d'un message.
 *
 * Seul le corps est modifiable : ni l'auteur, ni la pièce jointe, ni le `seq`,
 * ni la cible d'une réponse. Modifier l'un de ces champs reviendrait à réécrire
 * l'histoire du fil au lieu de corriger un propos.
 */
class UpdateConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation est appliquée dans le service (auteur du message
        // uniquement) après résolution du fil via `findForActor()`.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
