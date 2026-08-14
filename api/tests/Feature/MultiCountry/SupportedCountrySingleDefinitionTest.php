<?php

declare(strict_types=1);

namespace Tests\Feature\MultiCountry;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Rules\SupportedCountry;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MULTI-PAYS (#1951) — une seule définition du « pays supporté » :
 * le registre d'affichage (CountryDefaults, 21 codes) ne suffit pas — la
 * validation vérifie la DISPONIBILITÉ des règles de paie (résolveur,
 * invariant 3). Les listes `in:` hardcodées des contrôleurs sont remplacées
 * par le contrat partagé `CountryRulesResolver::payrollCountryCodes()`.
 */
class SupportedCountrySingleDefinitionTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    // ── Règle de validation : registre + règles disponibles ───────────────

    public function test_supported_country_rule_accepts_payroll_countries(): void
    {
        foreach (['DZ', 'CI', 'SN', 'BF', 'CM', 'GA'] as $code) {
            $validator = Validator::make(['country' => $code], ['country' => [new SupportedCountry]]);
            $this->assertFalse($validator->fails(), "{$code} devrait être supporté (règles présentes)");
        }
    }

    public function test_supported_country_rule_rejects_display_only_country(): void
    {
        // GB/US sont dans le registre d'affichage (CountryDefaults) mais sans
        // règles de paie : la validation doit les REJETER (invariant 3).
        foreach (['GB', 'US'] as $code) {
            $validator = Validator::make(['country' => $code], ['country' => [new SupportedCountry]]);
            $this->assertTrue($validator->fails(), "{$code} ne devrait PAS être accepté (pas de règles de paie)");
            $this->assertArrayHasKey('country', $validator->errors()->toArray());
        }
    }

    public function test_supported_country_rule_rejects_unknown_code(): void
    {
        $validator = Validator::make(['country' => 'ZZ'], ['country' => [new SupportedCountry]]);
        $this->assertTrue($validator->fails());
    }

    // ── Contrat partagé : les 3 listes ont fusionné en une ────────────────

    public function test_payroll_country_codes_is_the_single_source(): void
    {
        $codes = CountryRulesResolver::payrollCountryCodes();

        // Les pays avec règles sont présents.
        foreach (['DZ', 'MA', 'TN', 'FR', 'TR', 'SN', 'CM', 'CI', 'BF', 'CA'] as $code) {
            $this->assertContains($code, $codes);
        }

        // Les pays sans règles (display only) sont ABSENTS.
        $this->assertNotContains('GB', $codes);
        $this->assertNotContains('US', $codes);

        // Tous les codes du contrat sont résolvables par le résolveur.
        $resolver = new CountryRulesResolver;
        foreach ($codes as $code) {
            $this->assertTrue($resolver->supports($code), "{$code} annoncé supporté mais non résolvable");
        }
    }

    // ── Bout en bout : run paie refusé pour un pays sans règles ───────────

    public function test_payroll_run_rejected_for_display_only_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York']);
        Sanctum::actingAs($this->makeManager($company));

        // Le tenant est US (registre display) mais SANS règles de paie :
        // la création de run doit être refusée (SupportedCountry strict).
        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'US',
        ])->assertUnprocessable()->assertJsonValidationErrors('country_code');
    }

    public function test_payroll_run_accepted_for_payroll_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $this->postJson('/api/v1/payroll-runs', [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'DZ',
        ])->assertCreated();
    }
}
