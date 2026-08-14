<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Issue #1811 — CRUD jours fériés par pays.
 *
 * Deux rôles :
 *  - super-admin (`/api/v1/admin/public-holidays`) : gère les fériés
 *    nationaux (company_id = NULL), tous pays ;
 *  - manager `principal` (`/api/v1/public-holidays`) : ne peut créer/modifier
 *    que les fériés de SA société (company_id forcé) ; les fériés nationaux
 *    restent en lecture seule.
 */
class PublicHolidayController extends Controller
{
    public function __construct(private readonly PublicHolidayService $holidays)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $countryCode = strtoupper((string) $request->input('country_code', 'DZ'));
        $year = (int) $request->input('year', (int) now()->year);

        $companyId = $this->companyScope($request);

        $query = PublicHoliday::query()
            ->where('country_code', $countryCode)
            ->where('year', $year);

        // super-admin voit tout (national + entreprises) ; principal ne voit
        // que les nationaux + les siens.
        if ($companyId !== null) {
            $query->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
        }

        $items = $query->orderBy('date')->get()->map(fn (PublicHoliday $h): array => [
            'id' => $h->id,
            'company_id' => $h->company_id,
            'country_code' => $h->country_code,
            'name' => $h->name,
            'date' => $h->date->toDateString(),
            'year' => $h->year,
            'is_recurring' => $h->is_recurring,
            'month_day' => $h->month_day,
            'holiday_type' => $h->holiday_type,
        ]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $data['company_id'] = $this->companyScope($request);
        $data['created_by'] = $request->user()?->id;

        $holiday = PublicHoliday::create($data);

        $this->holidays->forget($data['country_code'], (int) $data['year'], $data['company_id']);

        return response()->json(['data' => $this->serialize($holiday)], 201);
    }

    public function update(Request $request, int $publicHoliday): JsonResponse
    {
        /** @var PublicHoliday $holiday */
        $holiday = PublicHoliday::query()->findOrFail($publicHoliday);
        $this->authorizeWrite($request, $holiday);

        $data = $this->validatePayload($request);
        $data['company_id'] = $holiday->company_id; // inchangé : le scope est verrouillé

        $holiday->update($data);
        $holiday->refresh();

        $this->holidays->forget($holiday->country_code, (int) $holiday->year, $holiday->company_id);

        return response()->json(['data' => $this->serialize($holiday)]);
    }

    public function destroy(Request $request, int $publicHoliday): JsonResponse
    {
        /** @var PublicHoliday $holiday */
        $holiday = PublicHoliday::query()->findOrFail($publicHoliday);
        $this->authorizeWrite($request, $holiday);

        $countryCode = $holiday->country_code;
        $year = (int) $holiday->year;
        $companyId = $holiday->company_id;

        $holiday->delete();

        $this->holidays->forget($countryCode, $year, $companyId);

        return response()->json(null, 204);
    }

    /**
     * Super-admin → NULL (national) ; principal → sa société (uuid).
     */
    private function companyScope(Request $request): ?string
    {
        $user = $request->user();

        if ($user instanceof SuperAdmin) {
            return null;
        }

        if ($user instanceof Employee && $user->isPrincipal()) {
            return $user->company_id;
        }

        abort(403, __('payroll.public_holidays_admin_only'));
    }

    private function authorizeWrite(Request $request, PublicHoliday $holiday): void
    {
        $user = $request->user();

        if ($user instanceof SuperAdmin) {
            return;
        }

        // principal : uniquement ses fériés d'entreprise ; jamais un national.
        if ($user instanceof Employee
            && $user->isPrincipal()
            && $holiday->company_id !== null
            && $holiday->company_id === $user->company_id) {
            return;
        }

        abort(403, __('payroll.public_holidays_company_only'));
    }

    /**
     * @return array{country_code: string, name: string, date: string, year: int, is_recurring: bool, month_day: string|null, holiday_type: string}
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'is_recurring' => ['sometimes', 'boolean'],
            'month_day' => ['nullable', 'string', 'max:5', 'regex:/^\d{2}-\d{2}$/'],
            'holiday_type' => ['required', Rule::in(['fixed', 'islamic', 'christian', 'custom'])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PublicHoliday $holiday): array
    {
        return [
            'id' => $holiday->id,
            'company_id' => $holiday->company_id,
            'country_code' => $holiday->country_code,
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'year' => $holiday->year,
            'is_recurring' => $holiday->is_recurring,
            'month_day' => $holiday->month_day,
            'holiday_type' => $holiday->holiday_type,
        ];
    }
}
