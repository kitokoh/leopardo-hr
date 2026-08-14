<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use App\Modules\Payroll\Infrastructure\Services\IslamicCalendarService;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1812 — Calendrier islamique dynamique : mapping pays, durées et
 * confirmation des dates mobiles.
 */
class IslamicCalendarServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function service(): IslamicCalendarService
    {
        return new IslamicCalendarService(Cache::store());
    }

    private function seedCalendar(bool $confirmed = false): void
    {
        $rows = [
            ['eid_al_fitr', 2026, '2026-03-20', 1],
            ['eid_al_adha', 2026, '2026-05-27', 2],
            ['mawlid', 2026, '2026-08-24', 1],
            ['muharram', 2026, '2026-06-16', 1],
            ['tahmarit', 2026, '2026-07-04', 1],
        ];

        foreach ($rows as [$key, $year, $date, $duration]) {
            IslamicCalendar::create([
                'holiday_key' => $key,
                'year' => $year,
                'gregorian_date' => $date,
                'duration_days' => $duration,
                'source' => 'computed',
                'confirmed' => $confirmed,
            ]);
        }
    }

    public function test_holidays_mapped_correctly_per_country(): void
    {
        $this->seedCalendar();

        $dz = $this->service()->getHolidaysForCountry('DZ', 2026);
        // Tri par date grégorienne : fitr (03-20), adha (05-27), muharram (06-16), mawlid (08-24).
        $this->assertSame(
            ['eid_al_fitr', 'eid_al_adha', 'muharram', 'mawlid'],
            array_column($dz, 'holiday_key'),
        );

        $cm = $this->service()->getHolidaysForCountry('CM', 2026);
        $this->assertSame(
            ['eid_al_fitr', 'eid_al_adha', 'mawlid'],
            array_column($cm, 'holiday_key'),
        );

        $ci = $this->service()->getHolidaysForCountry('CI', 2026);
        $this->assertSame(
            ['eid_al_fitr', 'eid_al_adha', 'mawlid'],
            array_column($ci, 'holiday_key'),
        );

        $sn = $this->service()->getHolidaysForCountry('SN', 2026);
        // Tri par date grégorienne : tahmarit (07-04) avant mawlid (08-24).
        $this->assertSame(
            ['eid_al_fitr', 'eid_al_adha', 'tahmarit', 'mawlid'],
            array_column($sn, 'holiday_key'),
        );

        // Pays sans mapping (ex. FR) → aucune fête islamique.
        $this->assertSame([], $this->service()->getHolidaysForCountry('FR', 2026));
    }

    public function test_duration_days_applied(): void
    {
        // #1930 : le calcul (resolveForPayroll) ne consomme que les dates
        // confirmées — on seed un calendrier confirmé pour tester les durées.
        $this->seedCalendar(confirmed: true);

        // CM : Aïd el-Adha fêté 2 jours → 2026-05-27 ET 2026-05-28 chômés.
        $cm = $this->service()->resolveForPayroll('CM', 2026);
        $dates = array_column($cm, 'date');
        $this->assertContains('2026-05-27', $dates);
        $this->assertContains('2026-05-28', $dates);

        // CI : 1 seul jour pour l'Aïd el-Adha.
        $ci = $this->service()->resolveForPayroll('CI', 2026);
        $this->assertContains('2026-05-27', array_column($ci, 'date'));
        $this->assertNotContains('2026-05-28', array_column($ci, 'date'));

        // DZ : 4 fêtes → fitr 1j + adha 2j + mawlid 1j + muharram 1j = 5 jours.
        $this->assertCount(5, $this->service()->resolveForPayroll('DZ', 2026));
    }

    public function test_unconfirmed_for_year_returns_pending_dates(): void
    {
        $this->seedCalendar();

        IslamicCalendar::where('holiday_key', 'eid_al_fitr')->where('year', 2026)->update([
            'confirmed' => true,
            'source' => 'manual',
        ]);

        $pending = $this->service()->unconfirmedForYear(2026);
        $this->assertCount(4, $pending);
        $this->assertSame('eid_al_adha', $pending[0]['holiday_key']);
    }

    public function test_confirm_year_marks_all_dates_confirmed(): void
    {
        $this->seedCalendar();

        $count = $this->service()->confirmYear(2026, 42);

        $this->assertSame(5, $count);
        $this->assertSame(
            5,
            IslamicCalendar::query()->where('year', 2026)->where('confirmed', true)->count(),
        );

        // Idempotent : un second appel ne confirme plus rien.
        $this->assertSame(0, $this->service()->confirmYear(2026, 42));
    }

    public function test_update_changes_date_and_confirmation(): void
    {
        $this->seedCalendar();

        $this->service()->update('eid_al_fitr', 2026, [
            'gregorian_date' => '2026-03-21',
            'duration_days' => 1,
            'confirmed' => true,
            'source' => 'manual',
            'confirmed_by' => 7,
        ]);

        /** @var IslamicCalendar $holiday */
        $holiday = IslamicCalendar::query()->where('holiday_key', 'eid_al_fitr')->where('year', 2026)->firstOrFail();
        $this->assertSame('2026-03-21', $holiday->gregorian_date->toDateString());
        $this->assertTrue($holiday->confirmed);
        $this->assertSame(7, $holiday->confirmed_by);
        $this->assertSame('manual', $holiday->source);

        // Le cache du pays a été invalidé : la nouvelle date est servie.
        $dz = $this->service()->getHolidaysForCountry('DZ', 2026);
        $fitr = collect($dz)->firstWhere('holiday_key', 'eid_al_fitr');
        $this->assertNotNull($fitr);
        $this->assertSame('2026-03-21', $fitr['date']);
    }

    public function test_working_days_uses_islamic_calendar_via_public_service(): void
    {
        // #1930 : seules les dates confirmées entrent dans le calcul des jours
        // ouvrés — un calendrier non confirmé ne doit RIEN retirer (fallback).
        $this->seedCalendar(confirmed: true);

        // Semaine du 2026-05-25 au 2026-05-29 (lun→ven, repos sam/dim).
        // CM : 27 et 28 chômés (Aïd 2 jours) → 3 jours ouvrés.
        $days = $this->app->make(PublicHolidayService::class)
            ->workingDaysBetween(
                Carbon::parse('2026-05-25'),
                Carbon::parse('2026-05-29'),
                'CM',
                restDays: [6, 7],
            );

        $this->assertSame(3.0, $days);
    }

    public function test_unconfirmed_dates_excluded_from_payroll_resolution(): void
    {
        // Issue #1930 [P1] — les dates approximatives (confirmed = false,
        // source 'computed' du seeder) ne doivent PAS alimenter la paie.
        $this->seedCalendar();

        // Admin : la liste complète (confirmées + non confirmées) reste
        // visible pour permettre la validation.
        $this->assertCount(5, $this->service()->getHolidaysForCountry('DZ', 2026));

        // Paie : aucune date non confirmée ne ressort.
        $this->assertSame([], $this->service()->resolveForPayroll('DZ', 2026));

        // Un admin confirme l'année → les dates entrent dans le calcul.
        $this->service()->confirmYear(2026, 42);
        $resolved = $this->service()->resolveForPayroll('DZ', 2026);
        $this->assertCount(5, $resolved);
        $this->assertContains('2026-03-20', array_column($resolved, 'date'));

        // Confirmée de façon ciblée : seule cette fête entre dans le calcul.
        IslamicCalendar::query()->delete();
        $this->service()->update('eid_al_fitr', 2026, [
            'gregorian_date' => '2026-03-20',
            'duration_days' => 1,
            'confirmed' => true,
            'source' => 'manual',
            'confirmed_by' => 7,
        ]);

        $partial = $this->service()->resolveForPayroll('DZ', 2026);
        $this->assertCount(1, $partial);
        $this->assertSame('2026-03-20', $partial[0]['date']);
    }
}
