<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Issue #1872 — les messages de conformité (payroll.confidence.*) sont
 * présents et traduits dans les 4 locales supportées (FR/EN/AR/TR) : un
 * manager non technique doit recevoir un avertissement dans sa langue, quel
 * que soit le niveau de confiance de la juridiction.
 */
class PayrollConfidenceTranslationsTest extends TestCase
{
    public function test_confidence_keys_exist_and_are_translated_in_all_supported_locales(): void
    {
        $keys = ['label', 'production.message', 'pilot.message', 'placeholder.message', 'unknown.message'];

        foreach (['fr', 'en', 'ar', 'tr'] as $locale) {
            app()->setLocale($locale);

            foreach ($keys as $key) {
                $value = Lang::get('payroll.confidence.'.$key, ['country' => 'DZ']);

                $this->assertIsString($value, "[{$locale}] payroll.confidence.{$key} must resolve to a string");
                $this->assertNotSame('', $value, "[{$locale}] payroll.confidence.{$key} must not be empty");
                $this->assertNotSame(
                    'payroll.confidence.'.$key,
                    $value,
                    "[{$locale}] payroll.confidence.{$key} must exist in the catalog",
                );
            }
        }
    }

    public function test_confidence_messages_are_actually_localized_per_locale(): void
    {
        app()->setLocale('fr');
        $fr = Lang::get('payroll.confidence.pilot.message', ['country' => 'DZ']);

        app()->setLocale('en');
        $en = Lang::get('payroll.confidence.pilot.message', ['country' => 'DZ']);

        app()->setLocale('ar');
        $ar = Lang::get('payroll.confidence.pilot.message', ['country' => 'DZ']);

        $this->assertNotSame($en, $fr);
        $this->assertNotSame($en, $ar);
        $this->assertNotSame($fr, $ar);
    }
}
