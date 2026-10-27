<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement d'un incident équipement (FUEL-010, #5804).
 */
class StoreFuelIncidentRequest extends FormRequest
{
    /**
     * #8578 (D6) — l'autorisation doit trancher AVANT la validation.
     *
     * `authorize()` renvoyait `true` en dur, donc la validation s'exécutait
     * d'abord et un opérateur non autorisé recevait 422 au lieu de 403. Ce
     * n'est pas qu'une question d'ordre cosmétique : un 422 sur un payload vide
     * RÉVÈLE le schéma (quels champs sont requis) à un acteur qui n'a pas le
     * droit d'écrire. Le 403 d'abord est la seule réponse qui n'informe pas.
     *
     * La policy reste l'unique autorité ; on ne duplique pas la règle ici, on
     * la consulte plus tôt. Le contrôleur garde son `authorize()` : il est
     * idempotent et sert de garde pour les tests qui appellent le service
     * directement.
     */
    public function authorize(): bool
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        return $actor->can('create', FuelIncident::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'equipment_type' => ['nullable', Rule::in(FuelIncident::EQUIPMENT_TYPES)],
            'equipment_id' => ['nullable', 'integer'],
            'severity' => ['nullable', Rule::in(FuelIncident::SEVERITIES)],
            // #8578 (D5) — `category` est une vraie dimension d'analyse : un
            // incident de SECURITE et une pompe en panne ne se traitent pas
            // pareil et ne se comptent pas ensemble. La deduire du texte libre
            // ne marche pas ; allowlist fermee, defaut `equipment`.
            'category' => ['nullable', Rule::in(FuelIncident::CATEGORIES)],
            // #8578 — cle de rejeu : ecrite dans `external_id`, relue par
            // `report()`. Bornee en longueur pour ne pas servir de stockage
            // arbitraire.
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
            // Pièces jointes contrôlées (métadonnées uniquement).
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.file_name' => ['required', 'string', 'max:200'],
            'attachments.*.mime_type' => ['required', Rule::in(FuelIncidentAttachment::ALLOWED_MIME_TYPES)],
            'attachments.*.size_bytes' => ['required', 'integer', 'min:0', 'max:5242880'],
        ];
    }
}
