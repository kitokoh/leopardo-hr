<?php

declare(strict_types=1);

namespace App\AI\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #9753 — proposition d'action depuis un client MCP externe.
 *
 * Décision du dépôt (#9699 / #9706) : FormRequest partout, pas de
 * `$request->validate()` en ligne. Les règles vivent ici, la permission dans
 * `authorize()` — le contrôleur ne fait que orchestrer.
 */
class ProposeAIActionRequest extends FormRequest
{
    /**
     * L'autorisation fine (rôle + matrice de permissions par outil) dépend de
     * l'outil demandé : elle est appliquée par `ToolPermissionPolicy` dans le
     * contrôleur, APRÈS résolution du nom d'outil. Ici on ne fait que vérifier
     * que la requête est authentifiée — un refus RBAC doit renvoyer un code
     * stable `AI_TOOL_PERMISSION_DENIED` (403), pas un 403 générique de
     * FormRequest.
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
            'tool' => ['required', 'string', 'max:64'],
            'arguments' => ['nullable', 'array'],
        ];
    }
}
