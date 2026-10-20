<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #8747 [BOS-CHAT] — envoi d'un message.
 *
 * Deux évolutions par rapport au contrat historique PA2-COMM-002, toutes
 * deux ADDITIVES :
 *
 *  - `body` n'est `required` que si aucune pièce jointe n'est fournie (un
 *    message composé uniquement d'une pièce jointe est légitime) ; un message
 *    totalement vide reste rejeté ;
 *  - `client_message_id` et `reply_to_message_id` sont acceptés, facultatifs.
 *
 * La règle `mimes:` historique est conservée comme **premier** filtre bon
 * marché, mais elle ne fait PAS foi : `mimes` raisonne sur l'extension
 * déclarée. Le contrôle réel (`finfo` sur le fichier) vit dans
 * `ConversationService::ensureAttachmentAllowed()`, conformément au risque R6
 * de l'audit.
 */
class PostConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation réelle est portée par `ConversationService::findForActor()`
        // (scope tenant + appartenance au fil) puis par la policy de groupe.
        // Ce `true` ne dispense d'aucun contrôle : il évite seulement de dupliquer
        // ici une logique qui dépend du fil, absent de la requête.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            // #8747 — clé d'idempotence fournie par le client (UUID ou
            // équivalent). Facultative : les clients historiques continuent de
            // fonctionner, ils ne bénéficient simplement pas de l'anti-doublon.
            'client_message_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.:\-]+$/'],
            // #8747 — réponse à un message du même fil (vérifié dans le service).
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Le message doit contenir un texte ou une pièce jointe.',
            'client_message_id.regex' => 'La clé d\'idempotence ne doit contenir que des lettres, chiffres, points, deux-points, tirets ou underscores.',
        ];
    }
}
