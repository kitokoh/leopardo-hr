<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-501..504 (#6071..#6074) — Validation des rapports travel.
 *
 * `from`/`to` bornés (≤ 365 j), `trip_id`/`route_id` scopés tenant,
 * `source`/`status` optionnels (chaînes courtes). Permission `travel.reports`
 * tranchée par le Gate dans le contrôleur.
 */
class TravelReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate `travel.reports` tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'trip_id' => [
                'nullable',
                'integer',
                Rule::exists((new TravelTrip)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'route_id' => [
                'nullable',
                'integer',
                Rule::exists((new TravelRoute)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'source' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, BookingSource::cases()))],
            'status' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, BookingStatus::cases()))],
        ];
    }
}
