<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Déclenchement d'un rapprochement stock (FUEL-009, issue #5803).
 *
 * `run_date` optionnel (défaut : aujourd'hui, fuseau de la station côté
 * contrôleur). Le rapprochement est rejouable : un run existant pour la
 * même (station, date) est renvoyé sans recalcul.
 */
class RunFuelReconciliationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'run_date' => ['nullable', 'date', 'before_or_equal:today'],
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lancement d'un rapprochement de stock FuelStation (FUEL-009, #5803).
 *
 * `measured_close_minor` : jauge de clôture saisie par le manager
 * (facultative — sans jauge, le rapport se clôt en `pending_measurement`,
 * jamais d'écart silencieux). `tolerance_minor` : tolérance d'écart
 * (défaut max(50, 0,5 % du stock théorique)).
 */
class RunFuelReconciliationRequest extends FormRequest
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'station_id' => [
                'required',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'product_type' => ['required', 'string', 'max:40'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'measured_close_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'tolerance_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
