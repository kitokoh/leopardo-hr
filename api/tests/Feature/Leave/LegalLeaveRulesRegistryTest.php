<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Modules\Planning\Domain\Exceptions\UnsupportedLeaveCountryException;
use App\Modules\Planning\Infrastructure\Services\CountryRules\LegalLeaveRulesRegistry;
use Tests\TestCase;

/**
 * Issue #5289 — registre des règles légales de congés par pays (US4).
 *
 * Miroir du résolveur Payroll : résolution stricte des 4 pays supportés,
 * exception typée pour les pays inconnus — AUCUN fallback silencieux.
 */
class LegalLeaveRulesRegistryTest extends TestCase
{
    public function test_registry_resolves_dz_ma_tn_sn(): void
    {
        $expected = [
            'DZ' => ['annual' => 30.0, 'monthly' => 2.5],
            'MA' => ['annual' => 24.0, 'monthly' => 2.0],
            'TN' => ['annual' => 30.0, 'monthly' => 2.5],
            'SN' => ['annual' => 26.0, 'monthly' => 2.1667],
        ];

        foreach ($expected as $countryCode => $values) {
            $rule = LegalLeaveRulesRegistry::resolve($countryCode);

            $this->assertSame($countryCode, $rule->countryCode());
            $this->assertSame($values['annual'], $rule->legalAnnualDays());
            $this->assertSame($values['monthly'], $rule->accrualDaysPerMonth());
            $this->assertNotSame('', $rule->legalSource(), "Source légale manquante pour {$countryCode}");
            $this->assertSame('pilot', $rule->confidenceLevel());
        }
    }

    public function test_registry_normalizes_country_case(): void
    {
        $this->assertSame('DZ', LegalLeaveRulesRegistry::resolve('dz')->countryCode());
        $this->assertTrue(LegalLeaveRulesRegistry::has('dz'));
        $this->assertFalse(LegalLeaveRulesRegistry::has('XX'));
    }

    public function test_registry_throws_for_unsupported_country(): void
    {
        $this->expectException(UnsupportedLeaveCountryException::class);

        LegalLeaveRulesRegistry::resolve('XX');
    }

    public function test_registry_has_method_matches_resolve(): void
    {
        foreach (['DZ', 'MA', 'TN', 'SN'] as $countryCode) {
            $this->assertTrue(LegalLeaveRulesRegistry::has($countryCode));
            $this->assertTrue(isset(LegalLeaveRulesRegistry::all()[$countryCode]));
        }
    }
}
