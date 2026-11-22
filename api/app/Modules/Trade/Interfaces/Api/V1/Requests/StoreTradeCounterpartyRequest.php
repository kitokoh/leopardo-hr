<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une contrepartie Trade — BC-36 TRADE, #9780.
 *
 * ## `roles` : au moins un rôle, jamais un rôle inventé
 *
 * Une contrepartie sans rôle n'a aucun sens métier : elle ne peut être ni
 * invitée à une RFQ (`supplier`), ni destinataire d'une offre (`buyer`), ni
 * sollicitée pour une cotation transport (`carrier`/`forwarder`). Le registre
 * `TradeCounterpartyRole` est fermé — une valeur hors registre est refusée en
 * validation, jamais ignorée silencieusement.
 *
 * ## `verification_status` n'est PAS accepté ici
 *
 * Le statut de vérification est un **acte distinct** (`POST .../verify`) qui
 * trace QUI a vérifié et QUAND. L'accepter à la création permettrait de
 * s'auto-déclarer « verified » en une requête, ce qui viderait la garantie de
 * son sens.
 *
 * ## `company_id` n'est jamais accepté
 *
 * Posé par le trait `BelongsToCompany` depuis le contexte tenant (anti-spoof
 * #7646) : un client ne choisit pas le tenant d'écriture.
 */
final class StoreTradeCounterpartyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(TradeCounterpartyRole::values())],
            'capabilities' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Liaison CRM optionnelle : un identifiant, jamais un objet.
            'crm_account_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Une contrepartie doit porter au moins un rôle métier.',
            'roles.min' => 'Une contrepartie doit porter au moins un rôle métier.',
            'roles.*.in' => 'Rôle inconnu : seuls les rôles du registre Trade sont acceptés.',
        ];
    }

    /**
     * Rôles dédoublonnés (le tableau jsonb n'a pas de contrainte d'unicité).
     *
     * @return list<string>
     */
    public function roles(): array
    {
        /** @var array<int, string> $raw */
        $raw = $this->validated('roles', []);

        return array_values(array_unique($raw));
    }
}
