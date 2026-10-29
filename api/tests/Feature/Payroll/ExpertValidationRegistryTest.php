<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\UnvalidatedConfidenceLevelException;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Modules\Payroll\Infrastructure\Services\ExpertValidationRegistry;
use Tests\TestCase;

/**
 * #9054 (BOS-006V2-8) — la règle « aucun pays en `production` sans fiche
 * experte signée » est désormais OPPOSABLE dans le code, pas seulement
 * documentée.
 *
 * Couverture : fiche absente ⇒ refus, fiche non signée ⇒ refus, fiche expirée
 * ⇒ refus, fiche valide ⇒ acceptation, `pilot`/`placeholder` ⇒ jamais bloqués,
 * et le câblage réel du resolver.
 */
class ExpertValidationRegistryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/expert-validation-'.bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    /** Écrit un registre de test et retourne le registre pointant dessus. */
    private function registryWith(string $markdown): ExpertValidationRegistry
    {
        $path = $this->tmpDir.'/VALIDATION_EXPERTE.md';
        file_put_contents($path, $markdown);

        return new ExpertValidationRegistry($path);
    }

    /** Règles factices avec un niveau de confiance imposé. */
    private function rules(string $country, string $level): CountryRulesInterface
    {
        /** @var CountryRulesInterface&\Mockery\MockInterface $rules */
        $rules = \Mockery::mock(CountryRulesInterface::class);
        $rules->shouldReceive('countryCode')->andReturn($country);
        $rules->shouldReceive('confidenceLevel')->andReturn($level);

        return $rules;
    }

    public function test_production_without_any_sheet_is_refused(): void
    {
        $registry = $this->registryWith("| Pays | Signée le | Expert | Périmètre |\n|---|---|---|---|\n");

        $this->expectException(UnvalidatedConfidenceLevelException::class);
        $registry->assertClaimIsBacked($this->rules('CM', 'production'));
    }

    public function test_production_with_unsigned_sheet_is_refused(): void
    {
        $registry = $this->registryWith("| CM |  |  | Paie |\n");

        $this->expectException(UnvalidatedConfidenceLevelException::class);
        $registry->assertClaimIsBacked($this->rules('CM', 'production'));
    }

    public function test_production_with_expired_sheet_is_refused(): void
    {
        $registry = $this->registryWith("| CM | 2024-01-15 | Expert ONECCA | Paie |\n");

        $this->expectException(UnvalidatedConfidenceLevelException::class);
        $registry->assertClaimIsBacked($this->rules('CM', 'production'));
    }

    public function test_production_with_valid_sheet_is_accepted(): void
    {
        $signedOn = date('Y-m-d', strtotime('-1 month'));
        $registry = $this->registryWith("| CM | {$signedOn} | Expert ONECCA | Paie |\n");

        $registry->assertClaimIsBacked($this->rules('CM', 'production'));

        $this->assertTrue($registry->isValidated('CM'));
        $this->assertSame(['CM'], $registry->validatedCountries());
    }

    public function test_pilot_and_placeholder_are_never_blocked(): void
    {
        $registry = $this->registryWith("| Pays | Signée le | Expert | Périmètre |\n|---|---|---|---|\n");

        $registry->assertClaimIsBacked($this->rules('FR', 'pilot'));
        $registry->assertClaimIsBacked($this->rules('TD', 'placeholder'));

        $this->assertFalse($registry->isValidated('FR'));
    }

    public function test_sheet_for_another_country_does_not_unlock_this_one(): void
    {
        $signedOn = date('Y-m-d', strtotime('-1 month'));
        $registry = $this->registryWith("| SN | {$signedOn} | Expert | Paie |\n");

        $this->expectException(UnvalidatedConfidenceLevelException::class);
        $registry->assertClaimIsBacked($this->rules('CM', 'production'));
    }

    public function test_repository_registry_accepts_senegal_production(): void
    {
        // Le registre réel du dépôt doit couvrir le seul pays `production`.
        $registry = new ExpertValidationRegistry;

        $this->assertTrue(
            $registry->isValidated('SN'),
            'Le Sénégal est en production : sa fiche signée doit être présente dans docs/payroll/VALIDATION_EXPERTE.md'
        );
        $registry->assertClaimIsBacked(new SenegalPayrollRules);
    }

    public function test_resolver_enforces_the_gate_on_real_rules(): void
    {
        // SN (production, fiche valide) passe ; un pays pilot passe aussi.
        $resolver = new CountryRulesResolver;

        $this->assertSame('SN', $resolver->resolve('SN')->countryCode());
        $this->assertSame('pilot', $resolver->resolve('FR')->confidenceLevel());
    }
}
