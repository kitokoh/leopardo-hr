<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Programme FOCUS — F-13/F-14 : tests unitaires du module Payroll.
 *
 * Ces tests ne nécessitent pas de base de données (AbstractCountryRules::taxSlabs()
 * retombe sur les barèmes par défaut si le schéma est absent — voir le try/catch
 * de resolveTaxSlabsFromDatabase()). Les méthodes couvertes ici :
 *  — PayrollCalculator::getRules() (wiring pays)
 *  — PayrollCalculator::__construct() injection personnalisée
 *  — Méthodes utilitaires de chaque CountryRules (timezone, weeklyRestDays, etc.)
 */
class PayrollCalculatorUnitTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  PayrollCalculator — wiring & getRules()                            //
    // ------------------------------------------------------------------ //

    public function test_get_rules_returns_correct_instance_for_dz(): void
    {
        $calc = new PayrollCalculator();
        $this->assertInstanceOf(AlgeriaPayrollRules::class, $calc->getRules('DZ'));
    }

    public function test_get_rules_returns_correct_instance_for_ma(): void
    {
        $this->assertInstanceOf(MoroccoPayrollRules::class, (new PayrollCalculator())->getRules('MA'));
    }

    public function test_get_rules_returns_correct_instance_for_tn(): void
    {
        $this->assertInstanceOf(TunisiaPayrollRules::class, (new PayrollCalculator())->getRules('TN'));
    }

    public function test_get_rules_returns_correct_instance_for_fr(): void
    {
        $this->assertInstanceOf(FrancePayrollRules::class, (new PayrollCalculator())->getRules('FR'));
    }

    public function test_get_rules_returns_correct_instance_for_ca(): void
    {
        $this->assertInstanceOf(CanadaPayrollRules::class, (new PayrollCalculator())->getRules('CA'));
    }

    public function test_get_rules_throws_for_unknown_country(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PayrollCalculator())->getRules('XX');
    }

    public function test_constructor_accepts_custom_rules(): void
    {
        $custom = new AlgeriaPayrollRules();
        $calc = new PayrollCalculator([$custom]);
        $this->assertSame($custom, $calc->getRules('DZ'));
    }

    // ------------------------------------------------------------------ //
    //  AlgeriaPayrollRules — metadata                                     //
    // ------------------------------------------------------------------ //

    public function test_algeria_country_code(): void
    {
        $this->assertSame('DZ', (new AlgeriaPayrollRules())->countryCode());
    }

    public function test_algeria_currency(): void
    {
        $this->assertSame('DZD', (new AlgeriaPayrollRules())->currency());
    }

    public function test_algeria_minimum_wage(): void
    {
        $this->assertSame(20000.0, (new AlgeriaPayrollRules())->minimumWage());
    }

    public function test_algeria_timezone(): void
    {
        $this->assertSame('Africa/Algiers', (new AlgeriaPayrollRules())->timezone());
    }

    public function test_algeria_weekly_rest_days(): void
    {
        $this->assertSame([5, 6], (new AlgeriaPayrollRules())->weeklyRestDays());
    }

    public function test_algeria_supported_pay_cycles(): void
    {
        $cycles = (new AlgeriaPayrollRules())->supportedPayCycles();
        $this->assertContains('monthly', $cycles);
        $this->assertContains('weekly', $cycles);
    }

    public function test_algeria_confidence_level(): void
    {
        $this->assertSame('pilot', (new AlgeriaPayrollRules())->confidenceLevel());
    }

    public function test_algeria_language(): void
    {
        $this->assertSame('fr', (new AlgeriaPayrollRules())->language());
    }

    public function test_algeria_overtime_threshold_weekly_hours(): void
    {
        $this->assertSame(40.0, (new AlgeriaPayrollRules())->overtimeThresholdWeeklyHours());
    }

    public function test_algeria_overtime_rate_tiers(): void
    {
        $tiers = (new AlgeriaPayrollRules())->overtimeRateTiers();
        $this->assertNotEmpty($tiers);
        $this->assertSame(1.5, $tiers[0]['multiplier']);
    }

    public function test_algeria_social_contributions_structure(): void
    {
        $contributions = (new AlgeriaPayrollRules())->socialContributions();
        $codes = array_column($contributions, 'code');
        $this->assertContains('CNAS_EMP', $codes);
        $this->assertContains('CNAS_PAT', $codes);
    }

    // ------------------------------------------------------------------ //
    //  MoroccoPayrollRules                                                //
    // ------------------------------------------------------------------ //

    public function test_morocco_country_code(): void
    {
        $this->assertSame('MA', (new MoroccoPayrollRules())->countryCode());
    }

    public function test_morocco_currency(): void
    {
        $this->assertSame('MAD', (new MoroccoPayrollRules())->currency());
    }

    public function test_morocco_income_tax_at_zero(): void
    {
        $this->assertSame(0.0, (new MoroccoPayrollRules())->calculateIncomeTax(0.0));
    }

    // ------------------------------------------------------------------ //
    //  TunisiaPayrollRules                                                //
    // ------------------------------------------------------------------ //

    public function test_tunisia_country_code(): void
    {
        $this->assertSame('TN', (new TunisiaPayrollRules())->countryCode());
    }

    public function test_tunisia_currency(): void
    {
        $this->assertSame('TND', (new TunisiaPayrollRules())->currency());
    }

    // ------------------------------------------------------------------ //
    //  FrancePayrollRules                                                 //
    // ------------------------------------------------------------------ //

    public function test_france_country_code(): void
    {
        $this->assertSame('FR', (new FrancePayrollRules())->countryCode());
    }

    public function test_france_currency(): void
    {
        $this->assertSame('EUR', (new FrancePayrollRules())->currency());
    }

    public function test_france_income_tax_below_threshold_is_zero(): void
    {
        // Annual threshold ÷ 12 months
        $this->assertSame(0.0, (new FrancePayrollRules())->calculateIncomeTax(900.0));
    }

    // ------------------------------------------------------------------ //
    //  TurkeyPayrollRules                                                 //
    // ------------------------------------------------------------------ //

    public function test_turkey_country_code(): void
    {
        $this->assertSame('TR', (new TurkeyPayrollRules())->countryCode());
    }

    public function test_turkey_currency(): void
    {
        $this->assertSame('TRY', (new TurkeyPayrollRules())->currency());
    }

    // ------------------------------------------------------------------ //
    //  SenegalPayrollRules                                                //
    // ------------------------------------------------------------------ //

    public function test_senegal_country_code(): void
    {
        $this->assertSame('SN', (new SenegalPayrollRules())->countryCode());
    }

    public function test_senegal_currency(): void
    {
        $this->assertSame('XOF', (new SenegalPayrollRules())->currency());
    }

    // ------------------------------------------------------------------ //
    //  CanadaPayrollRules                                                 //
    // ------------------------------------------------------------------ //

    public function test_canada_country_code(): void
    {
        $this->assertSame('CA', (new CanadaPayrollRules())->countryCode());
    }

    public function test_canada_currency(): void
    {
        $this->assertSame('CAD', (new CanadaPayrollRules())->currency());
    }

    public function test_canada_income_tax_returns_numeric(): void
    {
        $tax = (new CanadaPayrollRules())->calculateIncomeTax(5000.0);
        $this->assertIsFloat($tax);
        $this->assertGreaterThanOrEqual(0.0, $tax);
    }

    // ------------------------------------------------------------------ //
    //  CemacPayrollRules                                                  //
    // ------------------------------------------------------------------ //

    public function test_cemac_member_country_codes(): void
    {
        $codes = CemacPayrollRules::MEMBER_COUNTRY_CODES;
        $this->assertContains('CM', $codes); // Cameroon
        $this->assertContains('GA', $codes); // Gabon
    }

    public function test_cemac_for_member_country_returns_correct_code(): void
    {
        $rules = (new CemacPayrollRules())->forMemberCountry('CM');
        $this->assertSame('CM', $rules->countryCode());
    }

    public function test_cemac_currency_is_xaf(): void
    {
        $rules = (new CemacPayrollRules())->forMemberCountry('GA');
        $this->assertSame('XAF', $rules->currency());
    }

    // ------------------------------------------------------------------ //
    //  CedeaoPayrollRules                                                 //
    // ------------------------------------------------------------------ //

    public function test_cedeao_member_country_codes(): void
    {
        $codes = CedeaoPayrollRules::MEMBER_COUNTRY_CODES;
        $this->assertContains('CI', $codes); // Côte d'Ivoire
        $this->assertContains('ML', $codes); // Mali
    }

    public function test_cedeao_for_member_country_returns_correct_code(): void
    {
        $rules = (new CedeaoPayrollRules())->forMemberCountry('CI');
        $this->assertSame('CI', $rules->countryCode());
    }

    public function test_cedeao_currency_is_xof(): void
    {
        $rules = (new CedeaoPayrollRules())->forMemberCountry('BF');
        $this->assertSame('XOF', $rules->currency());
    }

    // ------------------------------------------------------------------ //
    //  forCompany() / asOf() scoping — AbstractCountryRules               //
    // ------------------------------------------------------------------ //

    public function test_for_company_returns_clone_with_company_id(): void
    {
        $rules    = new AlgeriaPayrollRules();
        $scoped   = $rules->forCompany('abc-123');
        // The original must be immutable
        $this->assertNotSame($rules, $scoped);
        // Both must still compute taxes correctly
        $this->assertSame(0.0, $scoped->calculateIncomeTax(20000.0));
    }

    public function test_as_of_returns_clone(): void
    {
        $rules  = new AlgeriaPayrollRules();
        $scoped = $rules->asOf(\Carbon\Carbon::now());
        $this->assertNotSame($rules, $scoped);
    }
}
