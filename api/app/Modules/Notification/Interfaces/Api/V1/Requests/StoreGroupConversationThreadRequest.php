<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use App\Modules\Notification\Domain\Models\ConversationThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8746 [BOS-CHAT] — création d'un fil de discussion de groupe.
 *
 * Validation uniquement : l'AUTORISATION (qui peut créer, avec qui) est
 * appliquée par `ConversationService::createGroup()`, qui résout les membres
 * dans le tenant de l'auteur. Un `participant_ids` contenant un employé d'un
 * autre tenant produit une 422, jamais un ajout silencieux.
 */
class StoreGroupConversationThreadRequest extends FormRequest
{
    /**
     * L'authentification et le tenant sont garantis par les middlewares de
     * route (`auth:sanctum`, `tenant`). L'autorisation fine reste serveur.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'participant_ids' => ['required', 'array', 'min:1', 'max:49'],
            'participant_ids.*' => ['integer', 'distinct'],
            // Référence typée vers un contexte métier : documentaire, sans
            // effet sur les droits d'accès.
            'subject_type' => ['nullable', 'string', Rule::in(ConversationThread::SUBJECT_TYPES)],
            'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_type'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participant_ids.required' => 'Un groupe doit contenir au moins un autre participant.',
            'participant_ids.max' => 'Un groupe est limité à 50 participants.',
            'participant_ids.*.distinct' => 'Un participant ne peut être ajouté qu\'une seule fois.',
        ];
    }
}
