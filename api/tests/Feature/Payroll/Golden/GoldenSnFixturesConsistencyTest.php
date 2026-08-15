<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Support\SnPayrollFixtures;

/**
 * Issue #2541 — auto-vérifie que les fixtures SN centralisées
 * (SnPayrollFixtures) restent alignées sur le moteur. Si un changement de
 * taux dans SenegalPayrollRules n'est pas répercuté dans les fixtures, ce
 * test échoue en nommant la valeur dérivée — au lieu de casser 6 suites.
 */
class GoldenSnFixturesConsistencyTest extends TestCase
{
    private function rules(): SenegalPayrollRules
    {
        return new SenegalPayrollRules;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    public static function chargesProvider(): array
    {
        return SnPayrollFixtures::charges();
    }

    #[DataProvider('chargesProvider')]
    public function test_fixture_charges_match_engine(float $gross, float $employee, float $employer, float $trimf): void
    {
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges($gross);

        $this->assertSame($employee, $charges['employee'], "charges salariales pour brut {$gross}");
        $this->assertSame($employer, $charges['employer'], "charges patronales pour brut {$gross}");
        $this->assertSame($trimf, $rules->calculateBracketTax($gross), "TRIMF pour brut {$gross}");
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    public static function trimfProvider(): array
    {
        return SnPayrollFixtures::trimfBrackets();
    }

    #[DataProvider('trimfProvider')]
    public function test_fixture_trimf_brackets_match_engine(float $gross, float $expectedTrimf): void
    {
        $this->assertSame($expectedTrimf, $this->rules()->calculateBracketTax($gross), "TRIMF pour brut {$gross}");
    }
}
