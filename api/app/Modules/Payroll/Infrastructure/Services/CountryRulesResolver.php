<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\CountryRulesContextMismatchException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\UnitedKingdomPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\UnitedStatesPayrollRules;
use Illuminate\Support\Carbon;

/**
 * MULTI-PAYS (#1868) — résolveur UNIQUE des règles pays.
 *
 * Point d'entrée unique pour résoudre les règles de paie d'un pays : la
 * map pays → règles vit ici (et nulle part ailleurs). Le résolveur :
 *   1. normalise le code pays (majuscules) ;
 *   2. refuse les pays inconnus avec une erreur métier typée
 *      (`UnsupportedCountryRulesException`, 422) — AUCUN fallback
 *      silencieux vers DZ ou une autre juridiction ;
 *   3. vérifie que la règle résolue expose bien le pays demandé
 *      (`CountryRulesContextMismatchException` sinon) ;
 *   4. applique l'override entreprise (`forCompany`) et la période
 *      d'effet (`asOf`) quand fournis.
 */
class CountryRulesResolver
{
    /** @var array<string, CountryRulesInterface> */
    private array $rulesMap;

    /**
     * @param  iterable<CountryRulesInterface>  $countryRules  règles custom (tests) ; vide → registre par défaut
     */
    public function __construct(iterable $countryRules = [])
    {
        $this->rulesMap = [];

        foreach ($countryRules as $rule) {
            $this->rulesMap[strtoupper($rule->countryCode())] = $rule;
        }

        if ($this->rulesMap === []) {
            $this->rulesMap = self::defaultRulesMap();
        }
    }

    /**
     * Registre par défaut : toutes les implémentations enregistrées du
     * moteur (y compris les zones CEMAC/CEDEAO éclatées par membre).
     *
     * @return array<string, CountryRulesInterface>
     */
    public static function defaultRulesMap(): array
    {
        $map = [
            'DZ' => new AlgeriaPayrollRules,
            'MA' => new MoroccoPayrollRules,
            'TN' => new TunisiaPayrollRules,
            'FR' => new FrancePayrollRules,
            'TR' => new TurkeyPayrollRules,
            'SN' => new SenegalPayrollRules,
        ];

        // CEMAC zone (PA2-COUNTRY-007) : une instance par État membre.
        foreach (CemacPayrollRules::MEMBER_COUNTRY_CODES as $memberCountryCode) {
            $map[$memberCountryCode] = (new CemacPayrollRules)->forMemberCountry($memberCountryCode);
        }

        // CEDEAO/UEMOA zone (PA2-COUNTRY-008) : une instance par membre XOF
        // (SN a sa propre classe dédiée, non dupliquée ici).
        foreach (CedeaoPayrollRules::MEMBER_COUNTRY_CODES as $memberCountryCode) {
            $map[$memberCountryCode] = (new CedeaoPayrollRules)->forMemberCountry($memberCountryCode);
        }

        // Canada (PA2-COUNTRY-009) : province = raffinement optionnel.
        $map['CA'] = new CanadaPayrollRules;

        // Packs EN (#5255) : GB (PAYE/NI) et US (fédéral) — pilotes 2026-27.
        $map['GB'] = new UnitedKingdomPayrollRules;
        $map['US'] = new UnitedStatesPayrollRules;

        return $map;
    }

    public function supports(string $countryCode): bool
    {
        return isset($this->rulesMap[strtoupper(trim($countryCode))]);
    }

    /**
     * @return list<string>
     */
    public function supportedCountryCodes(): array
    {
        $codes = array_keys($this->rulesMap);
        sort($codes);

        return $codes;
    }

    /**
     * Résout les règles de paie d'un pays, scopes entreprise/période inclus.
     *
     * @throws UnsupportedCountryRulesException si le pays n'est pas enregistré
     * @throws CountryRulesContextMismatchException si la règle résolue ne
     *                                              correspond pas au pays demandé
     */
    public function resolve(string $countryCode, ?string $companyId = null, ?\DateTimeInterface $asOf = null): CountryRulesInterface
    {
        $normalized = strtoupper(trim($countryCode));

        if (! isset($this->rulesMap[$normalized])) {
            throw new UnsupportedCountryRulesException($countryCode);
        }

        $rules = $this->rulesMap[$normalized];

        if ($rules->countryCode() !== $normalized) {
            throw new CountryRulesContextMismatchException($normalized, $rules->countryCode());
        }

        if ($companyId !== null) {
            $rules = $rules->forCompany($companyId);
        }

        if ($asOf !== null) {
            $rules = $rules->asOf(Carbon::instance($asOf));
        }

        return $rules;
    }
}
