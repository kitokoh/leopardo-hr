<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use Tests\TestCase;

/**
 * Issue #5269 — i18n ×4 du module pointage : parité des catalogues
 * attendance.* et présence des messages API localisés dans les 4 locales.
 */
class AttendanceI18nTest extends TestCase
{
    private const LANGS = ['fr', 'en', 'tr', 'ar'];

    public function test_attendance_catalog_keys_are_identical_across_all_four_locales(): void
    {
        $keySets = [];
        foreach (self::LANGS as $lang) {
            $content = (string) file_get_contents(lang_path("{$lang}/attendance.php"));
            preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>/m", $content, $matches);
            $keySets[$lang] = $matches[1];
        }

        $base = $keySets['fr'];
        sort($base);

        foreach (self::LANGS as $lang) {
            $keys = $keySets[$lang];
            sort($keys);
            $this->assertSame($base, $keys, "attendance.php ({$lang}) : clés divergentes.");
        }

        $this->assertGreaterThanOrEqual(45, count($base), 'Le catalogue attendance.* a régressé sous 45 clés.');
    }

    /**
     * Les messages API localisés existent dans les 4 locales (contenu, pas
     * seulement les clés) — valeurs EN identiques aux littéraux historiques.
     */
    public function test_localized_api_messages_present_in_all_locales(): void
    {
        foreach (['workflow_deactivated', 'request_not_pending', 'calendar_disconnected', 'geo_event_processed', 'geo_event_no_session'] as $key) {
            foreach (self::LANGS as $lang) {
                $value = trans("attendance.{$key}", [], $lang);
                $this->assertNotSame("attendance.{$key}", $value, "attendance.{$key} manquant en {$lang}.");
            }
        }
    }

    public function test_english_values_match_historical_literals(): void
    {
        app()->setLocale('en');

        $this->assertSame('Workflow deactivated.', __('attendance.workflow_deactivated'));
        $this->assertSame('Request is not pending.', __('attendance.request_not_pending'));
        $this->assertSame('Calendar disconnected.', __('attendance.calendar_disconnected'));
        $this->assertSame('Geo event processed successfully.', __('attendance.geo_event_processed'));
    }
}
