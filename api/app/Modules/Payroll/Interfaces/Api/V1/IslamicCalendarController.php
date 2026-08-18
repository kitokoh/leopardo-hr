<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use App\Modules\Payroll\Infrastructure\Services\IslamicCalendarService;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Issue #1812 — Calendrier islamique dynamique (super-admin uniquement).
 *
 * Les dates des fêtes islamiques sont nationales : seul un `platform_admin`
 * (guard `super_admin_api`) peut les saisir/confirmer. Les managers
 * `principal` n'ont aucun accès à ces routes (elles ne sont exposées que dans
 * le groupe `/api/v1/admin`).
 */
class IslamicCalendarController extends Controller
{
    public function __construct(
        private readonly IslamicCalendarService $calendar,
        private readonly PublicHolidayService $publicHolidays,
    ) {}

    /**
     * Liste des fêtes islamiques d'une année, enrichies de leur applicabilité
     * par pays (le calcul des jours ouvrés de chaque pays s'en sert).
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->year);

        $entries = IslamicCalendar::query()
            ->where('year', $year)
            ->orderBy('gregorian_date')
            ->get()
            ->map(fn (IslamicCalendar $holiday): array => $this->serialize($holiday, countries: true))
            ->all();

        $unconfirmed = $this->calendar->unconfirmedForYear($year);

        return response()->json([
            'data' => $entries,
            'meta' => [
                'year' => $year,
                'unconfirmed_count' => count($unconfirmed),
                'unconfirmed' => $unconfirmed,
            ],
        ]);
    }

    /**
     * Met à jour une date islamique (date grégorienne, durée, statut confirmé).
     * Le `holiday_key` est vérifié contre la liste connue ; l'année est
     * bornée. Toute écriture invalide les caches paie des pays concernés.
     */
    public function update(Request $request, string $holidayKey, int $year): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        /** @var array{gregorian_date: string, duration_days: int, confirmed?: bool, source?: string} $data */
        $data = $request->validate([
            'gregorian_date' => ['required', 'date'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:5'],
            'confirmed' => ['sometimes', 'boolean'],
            'source' => ['sometimes', Rule::in(['manual', 'api', 'computed'])],
        ]);

        $holiday = $this->calendar->update($holidayKey, $year, [
            'gregorian_date' => $data['gregorian_date'],
            'duration_days' => (int) $data['duration_days'],
            'source' => $data['source'] ?? 'manual',
            'confirmed' => (bool) ($data['confirmed'] ?? true),
            'confirmed_by' => $request->user()?->id,
        ]);

        // Les fériés islamiques alimentent le calendrier de tous les pays :
        // invalidation large pour la paie (sécurité > micro-optimisation).
        // BUG #1897 : inclut les clés tenant-scopées (pas seulement nationale).
        foreach (array_keys((array) config('islamic_holidays_map.countries', [])) as $countryCode) {
            $this->publicHolidays->forgetAllScopes((string) $countryCode, $year);
        }

        return response()->json(['data' => $this->serialize($holiday)]);
    }

    /**
     * Confirme toutes les dates d'une année (une fois officielles connues).
     */
    public function confirmYear(Request $request, int $year): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        $confirmed = $this->calendar->confirmYear($year, (int) $request->user()?->id);

        // Invalidation paie large (mêmes raisons que update()).
        // BUG #1897 : inclut les clés tenant-scopées (pas seulement nationale).
        foreach (array_keys((array) config('islamic_holidays_map.countries', [])) as $countryCode) {
            $this->publicHolidays->forgetAllScopes((string) $countryCode, $year);
        }

        return response()->json([
            'data' => [
                'year' => $year,
                'confirmed_count' => $confirmed,
            ],
        ]);
    }

    private function assertPlatformAdmin(Request $request): void
    {
        if (! $request->user() instanceof SuperAdmin) {
            // #4690 : le message HTTP doit rester localisé, y compris dans
            // le champ `message` consommé par les clients legacy.
            abort(403, __('errors.ISLAMIC_CALENDAR_PLATFORM_ONLY'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(IslamicCalendar $holiday, bool $countries = false): array
    {
        /** @var array<string, string> $labels */
        $labels = (array) config('islamic_holidays_map.labels', []);

        $payload = [
            'id' => $holiday->id,
            'holiday_key' => $holiday->holiday_key,
            'name' => $labels[$holiday->holiday_key] ?? $holiday->holiday_key,
            'year' => (int) $holiday->year,
            'gregorian_date' => $holiday->gregorian_date->toDateString(),
            'duration_days' => (int) $holiday->duration_days,
            'source' => $holiday->source,
            'confirmed' => $holiday->confirmed,
            'confirmed_by' => $holiday->confirmed_by,
        ];

        if ($countries) {
            $payload['countries'] = $this->countriesFor($holiday->holiday_key);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function countriesFor(string $holidayKey): array
    {
        /** @var array<string, array<string, array{duration?: int, name?: string}>> $countriesMap */
        $countriesMap = (array) config('islamic_holidays_map.countries', []);

        $countries = [];
        foreach ($countriesMap as $code => $festivities) {
            if (array_key_exists($holidayKey, $festivities)) {
                $countries[] = $code;
            }
        }

        return $countries;
    }
}
