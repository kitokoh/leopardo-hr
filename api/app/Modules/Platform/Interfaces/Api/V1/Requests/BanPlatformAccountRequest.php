<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #9815 — corps du bannissement d'un compte.
 *
 * Décision du dépôt : FormRequest partout (garde
 * `check-form-request-guard.py`). Les règles vivent ici, pas dans le
 * contrôleur — c'est ce qui rend la validation testable et réutilisable.
 *
 * `hours` absent ou `null` = bannissement **permanent**. La borne haute
 * (8 760 h = 1 an) n'est pas décorative : un bannissement « temporaire » de
 * dix ans est un bannissement permanent déguisé, et l'écrire comme tel rend
 * l'intention lisible dans l'audit.
 */
class BanPlatformAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation est portée par `platform.permission:accounts.ban`
        // (middleware de route) : la dupliquer ici créerait deux sources de
        // vérité qui peuvent diverger.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
