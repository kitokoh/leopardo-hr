<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use App\Support\CountryDefaults;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
    public function __construct(private readonly PublicHolidayService $holidays) {}

    public function index(Request $request): JsonResponse
    {
        $countryCode = strtoupper((string) $request->string('country_code', 'DZ'));
        $year = $request->integer('year', (int) now()->year);

        // Issue #1917 : Policy Laravel (super-admin ou principal).
        $this->authorize('viewAny', PublicHoliday::class);

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
        // Issue #1917 : Policy Laravel (super-admin ou principal).
        $this->authorize('create', PublicHoliday::class);

        $data = $this->validatePayload($request);
        $data['company_id'] = $this->companyScope($request);
        $this->assertUnique($data);
        $data['created_by'] = $request->user()?->id;

        try {
            // #4978 : savepoint — la violation unique attendue (course) est
            // rollbackée localement (évite 25P02 sur la transaction courante).
            $holiday = DB::transaction(fn (): PublicHoliday => PublicHoliday::create($data));
        } catch (QueryException $e) {
            // Issue #3811 : course entre assertUnique() et create() (contrainte
            // unique public_holidays_country_year_date_company_unique) — une
            // requête concurrente a inséré le même férié entre les deux.
            // 23505 = SQLSTATE unique_violation (pattern PartnerService #3238) :
            // réponse 422 identique à celle d'assertUnique, jamais de 500.
            if ($e->getCode() === '23505') {
                Log::warning('Public holiday race — concurrent create won: '.json_encode($data));

                throw ValidationException::withMessages([
                    'date' => 'A public holiday already exists for this country, year, date and company.',
                ]);
            }

            throw $e;
        }

        if (($data['company_id'] ?? null) === null) {
            // Férié NATIONAL : tous les tenants le voient → invalider tous les scopes (BUG #1897).
            $this->holidays->forgetAllScopes($data['country_code'], (int) $data['year']);
        } else {
            $this->holidays->forget($data['country_code'], (int) $data['year'], $data['company_id']);
        }

        return response()->json(['data' => $this->serialize($holiday)], 201);
    }

    public function update(Request $request, int $publicHoliday): JsonResponse
    {
        /** @var PublicHoliday $holiday */
        $holiday = PublicHoliday::query()->findOrFail($publicHoliday);
        // Issue #1917 : Policy Laravel (super-admin, ou principal sur ses fériés).
        $this->authorize('update', $holiday);

        $data = $this->validatePayload($request);
        $data['company_id'] = $holiday->company_id; // inchangé : le scope est verrouillé
        $this->assertUnique($data, (int) $holiday->id);

        $holiday->update($data);

        if ($holiday->company_id === null) {
            $this->holidays->forgetAllScopes($holiday->country_code, (int) $holiday->year);
        } else {
            $this->holidays->forget($holiday->country_code, (int) $holiday->year, (string) $holiday->company_id);
        }

        $holiday->refresh();

        return response()->json(['data' => $this->serialize($holiday)]);
    }

    public function destroy(Request $request, int $publicHoliday): JsonResponse
    {
        /** @var PublicHoliday $holiday */
        $holiday = PublicHoliday::query()->findOrFail($publicHoliday);
        // Issue #1917 : Policy Laravel (super-admin, ou principal sur ses fériés).
        $this->authorize('delete', $holiday);

        $countryCode = $holiday->country_code;
        $year = (int) $holiday->year;
        $companyId = $holiday->company_id;

        $holiday->delete();

        if ($companyId === null) {
            $this->holidays->forgetAllScopes($countryCode, $year);
        } else {
            $this->holidays->forget($countryCode, $year, $companyId);
        }

        return response()->json(null, 204);
    }

    /**
     * Super-admin → NULL (national) ; principal → sa société.
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

        abort(403, __('errors.FORBIDDEN'));
    }

    /**
     * @return array{country_code: string, name: string, date: string, year: int, is_recurring: bool, month_day: string|null, holiday_type: string}
     */
    private function validatePayload(Request $request): array
    {
        // Issue #1937 : pays allowlisté (registre #1867) + normalisé, year
        // recoupé avec date, month_day requis si récurrent et cohérent.
        // Normalisation AVANT validation : `Rule::in` est sensible à la casse,
        // un payload 'dz' serait rejeté 422 alors que la lecture uppercasse
        // (une ligne 'dz' serait invisible à la relecture sinon).
        $request->merge(['country_code' => strtoupper(trim((string) $request->input('country_code', '')))]);

        $validator = Validator::make($request->all(), [
            'country_code' => ['required', 'string', 'size:2', Rule::in(array_column(CountryDefaults::all(), 'country'))],
            'name' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'is_recurring' => ['sometimes', 'boolean'],
            'month_day' => ['nullable', 'required_if:is_recurring,true', 'string', 'max:5', 'regex:/^\d{2}-\d{2}$/'],
            'holiday_type' => ['required', Rule::in(['fixed', 'islamic', 'christian', 'custom'])],
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            // Si 'date' a déjà échoué, Carbon::parse lèverait une exception
            // (500) au lieu d'un 422 propre.
            if ($v->errors()->has('date')) {
                return;
            }

            $data = $v->getData();
            $date = isset($data['date']) ? Carbon::parse((string) $data['date']) : null;
            $year = (int) ($data['year'] ?? 0);

            // Recoupement date ↔ year : une date de 2026 ne peut pas porter
            // year=2025 (ligne invisible dans la lecture par année sinon).
            if ($date !== null && $date->year !== $year) {
                $v->errors()->add('year', __('errors.PUBLIC_HOLIDAY_YEAR_MISMATCH'));
            }

            // month_day (si fourni avec is_recurring) doit correspondre à la
            // date : férié récurrent stocké avec sa première occurrence.
            // (Lecture booléenne robuste : la règle `boolean` accepte la chaîne
            // "false"/"0" — un test sur la valeur brute serait un faux positif.)
            $isRecurring = filter_var($data['is_recurring'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($date !== null && $isRecurring && isset($data['month_day']) && $data['month_day'] !== '') {
                if ((string) $data['month_day'] !== $date->format('m-d')) {
                    $v->errors()->add('month_day', __('errors.PUBLIC_HOLIDAY_MONTH_DAY_MISMATCH'));
                }
            }
        });

        /** @var array{country_code: string, name: string, date: string, year: int, is_recurring: bool, month_day: string|null, holiday_type: string} $validated */
        $validated = $validator->validate();

        // Normalisation : pays en MAJUSCULES (une ligne 'dz' serait invisible
        // sinon — la lecture uppercasse).
        $validated['country_code'] = strtoupper((string) $validated['country_code']);

        return $validated;
    }

    /**
     * Issue #1937 — unicité (country_code, year, date, company_id) : un férié
     * national (company_id NULL) comme un férié d'entreprise. 422 propre AVANT
     * la contrainte DB (migration 000009).
     *
     * @param  array{country_code: string, year: int, date: string, company_id: string|null}  $data
     */
    private function assertUnique(array $data, ?int $ignoreId = null): void
    {
        $query = PublicHoliday::query()
            ->where('country_code', $data['country_code'])
            ->where('year', $data['year'])
            ->where('date', $data['date'])
            ->where('company_id', $data['company_id']);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'date' => 'A public holiday already exists for this country, year, date and company.',
            ]);
        }
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
