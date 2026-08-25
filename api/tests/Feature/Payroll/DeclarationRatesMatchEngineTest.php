<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Infrastructure\Services\CedeaoCnsDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CemacCnpsDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CnssDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\IpresDeclarationGenerator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Issue #2539 — les générateurs de déclaration CSV ne doivent PAS dupliquer
 * les taux/plafonds du moteur (SenegalPayrollRules / CedeaoPayrollRules /
 * CemacPayrollRules). Deux gardes :
 *
 *  1. SN (IpresDeclarationGenerator) et CI (CnssDeclarationGenerator) lisent
 *     désormais les taux depuis les règles pays — aucune constante RATE_* ni *CAP*
 *     ne doit réapparaître (garde structurelle par réflexion).
 *  2. GA/CG/BF/ML (CemacCnpsDeclarationGenerator / CedeaoCnsDeclarationGenerator)
 *     gardent encore des constantes : chaque constante est comparée aux règles
 *     pays par code — toute divergence moteur ↔ déclaration fait échouer le
 *     test (même classe de bug que la divergence CSS SN #2473).
 */
class DeclarationRatesMatchEngineTest extends TestCase
{
    public function test_sn_and_ci_generators_have_no_duplicated_rate_constants(): void
    {
        foreach ([IpresDeclarationGenerator::class, CnssDeclarationGenerator::class] as $class) {
            $constants = (new ReflectionClass($class))->getConstants();

            foreach ($constants as $name => $_) {
                $this->assertDoesNotMatchRegularExpression(
                    '/^(RATE_|.*_CAP$)/',
                    $name,
                    "{$class}::{$name} duplique un taux/plafond — lire les taux depuis les règles pays (#2539).",
                );
            }
        }

        // La boucle ci-dessus échoue si une constante dupliquée réapparaît — pas d'assertion supplémentaire requise.
    }

    /**
     * @dataProvider declarationConstantProvider
     *
     * @param  class-string  $generatorClass
     * @param  class-string  $rulesClass
     */
    public function test_generator_constant_matches_country_rules(
        string $generatorClass,
        string $rulesClass,
        string $memberCountryCode,
        string $constant,
        string $ruleCode,
        string $field,
    ): void {
        $generatorValue = (new ReflectionClass($generatorClass))->getConstant($constant);

        /** @var CountryRulesInterface $rules */
        $rules = new $rulesClass($memberCountryCode);
        $contrib = null;
        foreach ($rules->socialContributions() as $entry) {
            if ($entry['code'] === $ruleCode) {
                $contrib = $entry;
                break;
            }
        }

        $this->assertNotNull($contrib, "Code {$ruleCode} absent de {$rulesClass}::socialContributions().");
        $this->assertSame(
            (float) $generatorValue,
            (float) $contrib[$field],
            "{$generatorClass}::{$constant} ({$generatorValue}) != {$rulesClass}({$memberCountryCode})::{$ruleCode}.{$field} ({$contrib[$field]})",
        );
    }

    /**
     * @return array<string, array{0: class-string, 1: class-string, 2: string, 3: string, 4: string, 5: string}>
     */
    public static function declarationConstantProvider(): array
    {
        return [
            // GA — CemacCnpsDeclarationGenerator vs CemacPayrollRules('GA')
            'GA retraite emp' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'GA', 'GA_RATE_RETRAITE_EMP', 'CNSS_GA_RET_EMP', 'rate'],
            'GA retraite pat' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'GA', 'GA_RATE_RETRAITE_PAT', 'CNSS_GA_RET_PAT', 'rate'],
            'GA famille pat' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'GA', 'GA_RATE_FAMILLE_PAT', 'CNSS_GA_FAM_PAT', 'rate'],
            'GA AT pat' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'GA', 'GA_RATE_AT_PAT', 'CNSS_GA_AT_PAT', 'rate'],
            'GA cap' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'GA', 'GA_RETIREMENT_FAMILY_CAP', 'CNSS_GA_RET_EMP', 'cap'],
            // CG
            'CG retraite emp' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'CG', 'CG_RATE_RETRAITE_EMP', 'CNSS_CG_RET_EMP', 'rate'],
            'CG retraite pat' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'CG', 'CG_RATE_RETRAITE_PAT', 'CNSS_CG_RET_PAT', 'rate'],
            'CG famille pat' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'CG', 'CG_RATE_FAMILLE_PAT', 'CNSS_CG_FAM_PAT', 'rate'],
            'CG AT pat' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'CG', 'CG_RATE_AT_PAT', 'CNSS_CG_AT_PAT', 'rate'],
            'CG cap' => [CemacCnpsDeclarationGenerator::class, CemacPayrollRules::class, 'CG', 'CG_RETIREMENT_FAMILY_CAP', 'CNSS_CG_RET_EMP', 'cap'],
            // BF — CedeaoCnsDeclarationGenerator vs CedeaoPayrollRules('BF')
            'BF retraite emp' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'BF', 'BF_RATE_RETRAITE_EMP', 'CNSS_BF_RET_EMP', 'rate'],
            'BF retraite pat' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'BF', 'BF_RATE_RETRAITE_PAT', 'CNSS_BF_RET_PAT', 'rate'],
            'BF famille pat' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'BF', 'BF_RATE_FAMILLE_PAT', 'CNSS_BF_FAM_PAT', 'rate'],
            'BF AT pat' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'BF', 'BF_RATE_AT_PAT', 'CNSS_BF_AT_PAT', 'rate'],
            'BF cap' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'BF', 'BF_RETIREMENT_FAMILY_CAP', 'CNSS_BF_RET_EMP', 'cap'],
            // ML
            'ML retraite emp' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'ML', 'ML_RATE_RETRAITE_EMP', 'INPS_ML_RET_EMP', 'rate'],
            'ML retraite pat' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'ML', 'ML_RATE_RETRAITE_PAT', 'INPS_ML_RET_PAT', 'rate'],
            'ML famille pat' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'ML', 'ML_RATE_FAMILLE_PAT', 'INPS_ML_FAM_PAT', 'rate'],
            'ML AT pat' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'ML', 'ML_RATE_AT_PAT', 'INPS_ML_AT_PAT', 'rate'],
            'ML cap' => [CedeaoCnsDeclarationGenerator::class, CedeaoPayrollRules::class, 'ML', 'ML_RETIREMENT_CAP', 'INPS_ML_RET_EMP', 'cap'],
        ];
    }
}
