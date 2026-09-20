<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services\CountryRules;

use App\Modules\Planning\Domain\Contracts\LegalLeaveCountryRuleInterface;
use App\Modules\Planning\Domain\Exceptions\UnsupportedLeaveCountryException;

/**
 * Issue #5289 — registre des règles légales de congés par pays.
 *
 * Point d'entrée UNIQUE pour résoudre les règles légales de congés d'un pays
 * (miroir de `CountryRulesResolver` côté Payroll). Le registre :
 *   1. normalise le code pays (majuscules) ;
 *   2. refuse les pays inconnus avec une erreur métier typée
 *      (`UnsupportedLeaveCountryException`) — AUCUN fallback silencieux ;
 *   3. vérifie que la règle résolue expose bien le pays demandé.
 */
final class LegalLeaveRulesRegistry
{
    /** @var array<string, LegalLeaveCountryRuleInterface>|null */
    private static ?array $rulesMap = null;

    /**
     * Registre par défaut : les implémentations enregistrées du moteur.
     * DZ/MA/TN/SN d'abord (issue #5289), puis CEMAC/CEDEAO (issue #7930,
     * lot BC-06 — TD/CF/GQ en pilot prudent) et FR/TR/CA (issue #7931).
     *
     * @return array<string, LegalLeaveCountryRuleInterface>
     */
    public static function defaultRulesMap(): array
    {
        return [
            // Maghreb + Sénégal (issue #5289)
            'DZ' => new AlgeriaLegalLeaveRule,
            'MA' => new MoroccoLegalLeaveRule,
            'TN' => new TunisiaLegalLeaveRule,
            'SN' => new SenegalLegalLeaveRule,
            // CEMAC (issue #7930 — TD/CF/GQ en pilot prudent)
            'CM' => new CameroonLegalLeaveRule,
            'GA' => new GabonLegalLeaveRule,
            'CG' => new CongoLegalLeaveRule,
            'TD' => new ChadLegalLeaveRule,
            'CF' => new CentralAfricanRepublicLegalLeaveRule,
            'GQ' => new EquatorialGuineaLegalLeaveRule,
            // CEDEAO (issue #7930)
            'CI' => new IvoryCoastLegalLeaveRule,
            'BF' => new BurkinaFasoLegalLeaveRule,
            'ML' => new MaliLegalLeaveRule,
            'TG' => new TogoLegalLeaveRule,
            'BJ' => new BeninLegalLeaveRule,
            'NE' => new NigerLegalLeaveRule,
            // Europe / Amérique du Nord / Turquie (issue #7931)
            'FR' => new FranceLegalLeaveRule,
            'TR' => new TurkeyLegalLeaveRule,
            'CA' => new CanadaLegalLeaveRule,
        ];
    }

    /**
     * Résout la règle légale de congés d'un pays.
     *
     * @throws UnsupportedLeaveCountryException si le pays n'a pas de règle enregistrée
     */
    public static function resolve(string $countryCode): LegalLeaveCountryRuleInterface
    {
        $code = strtoupper(trim($countryCode));
        self::$rulesMap ??= self::defaultRulesMap();

        $rule = self::$rulesMap[$code] ?? null;
        if ($rule === null) {
            throw new UnsupportedLeaveCountryException($code);
        }

        if (strtoupper($rule->countryCode()) !== $code) {
            throw new \LogicException(
                sprintf('Règle mal enregistrée : pays demandé « %s » ≠ pays exposé « %s ».', $code, $rule->countryCode())
            );
        }

        return $rule;
    }

    /** Le pays dispose-t-il d'une règle légale de congés enregistrée ? */
    public static function has(string $countryCode): bool
    {
        self::$rulesMap ??= self::defaultRulesMap();

        return isset(self::$rulesMap[strtoupper(trim($countryCode))]);
    }

    /** @return array<string, LegalLeaveCountryRuleInterface> */
    public static function all(): array
    {
        return self::$rulesMap ??= self::defaultRulesMap();
    }
}
